<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

use WPSafeMode\Core\EnvironmentReport;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Principal;
use WPSafeMode\Security\Role;
use WPSafeMode\Security\SelfTestResult;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\User;

/**
 * The first-run wizard: token, owner, self-test, API key, finalize.
 *
 * The order is the security design, not a UI preference. The API key is the
 * only thing here worth stealing, so it is accepted last: after the user has
 * proved filesystem access, after an owner exists, and after the directory
 * that will hold it has been proven unreachable over HTTP.
 */
final class Installer
{
    public const STEP_TOKEN   = 'token';
    public const STEP_OWNER   = 'owner';
    public const STEP_API_KEY = 'api_key';
    public const STEP_DONE    = 'done';

    /**
     * StorageException codes for the refusals a user can act on, so callers
     * map causes without matching text. Each coded message is fixed and
     * carries no server path; anything uncoded is a fault, not a refusal.
     */
    public const ERR_WEAK_PASSWORD     = 1;
    public const ERR_BAD_LOGIN         = 2;
    public const ERR_OWNER_EXISTS      = 3;
    public const ERR_NO_OWNER          = 4;
    public const ERR_EMPTY_KEY         = 5;
    public const ERR_EXPOSED           = 6;
    public const ERR_ALREADY_INSTALLED = 7;

    private const TOKEN_FILE = 'install-token.php';

    private ?SelfTestResult $lastSelfTest = null;

    public function __construct(
        private Storage $storage,
        private KeyVault $vault,
        private PasswordHasher $hasher,
        private string $dataDir,
        private EnvironmentReport $env,
    ) {
    }

    public function state(): InstallState
    {
        $hasOwner = !$this->storage->users()->isEmpty();
        $hasApiKey = $this->storage->config()->has('api_key');
        $installed = $this->isInstalled();

        if ($installed) {
            $step = self::STEP_DONE;
        } elseif (!$hasOwner) {
            $step = is_file($this->dataDir . '/' . self::TOKEN_FILE) ? self::STEP_TOKEN : self::STEP_OWNER;
        } else {
            $step = self::STEP_API_KEY;
        }

        return new InstallState(
            $installed,
            $step,
            $hasOwner,
            $hasApiKey,
            $this->lastSelfTest,
            $this->vault->algorithm(),
            $this->env->wpRoot !== null
        );
    }

    /** Lets the installer's controller record an event without holding Storage. */
    public function audit(AuditEvent $event): void
    {
        $this->storage->auditLog()->record($event);
    }

    /**
     * The token proves whoever creates the owner can read the filesystem. A
     * public URL is discoverable, and without this whoever reaches an
     * unconfigured copy first would own it.
     */
    public function issueToken(): string
    {
        $this->assertNotInstalled();

        DataDir::ensure($this->dataDir);

        $path = $this->dataDir . '/' . self::TOKEN_FILE;
        $existing = GuardedJson::read($path);

        if (isset($existing['token']) && is_string($existing['token']) && $existing['token'] !== '') {
            return $existing['token'];
        }

        $token = strtoupper(bin2hex(random_bytes(8)));
        GuardedJson::write($path, ['token' => $token, 'created_at' => time()]);

        return $token;
    }

    public function verifyToken(string $presented): bool
    {
        // The gate is installed_at, not the token file: deleting the file is
        // best-effort and a token written back must open nothing.
        if ($this->isInstalled()) {
            return false;
        }

        $stored = GuardedJson::read($this->dataDir . '/' . self::TOKEN_FILE);

        if (!isset($stored['token']) || !is_string($stored['token'])) {
            return false;
        }

        return hash_equals($stored['token'], strtoupper(trim($presented)));
    }

    /**
     * @throws StorageException coded ERR_WEAK_PASSWORD, ERR_BAD_LOGIN or ERR_OWNER_EXISTS
     */
    public function createOwner(string $login, string $password): User
    {
        $weaknesses = PasswordHasher::weaknesses($password);

        if ($weaknesses !== []) {
            throw new StorageException(implode(' ', $weaknesses), self::ERR_WEAK_PASSWORD);
        }

        if (!User::isValidLogin(User::normalizeLogin($login))) {
            throw new StorageException('Invalid login.', self::ERR_BAD_LOGIN);
        }

        // Atomic in the store: two concurrent token-bearing requests cannot
        // both create an owner.
        $user = $this->storage->users()->createIfEmpty($login, Role::OWNER, $this->hasher->hash($password));

        if ($user === null) {
            throw new StorageException('This copy already has an owner.', self::ERR_OWNER_EXISTS);
        }

        return $user;
    }

    public function runSelfTest(string $baseUrl): SelfTestResult
    {
        return $this->lastSelfTest = WebAccessibilitySelfTest::run($this->dataDir, $baseUrl);
    }

    /**
     * @throws StorageException coded ERR_ALREADY_INSTALLED, ERR_NO_OWNER,
     *         ERR_EMPTY_KEY or ERR_EXPOSED for a refusal; uncoded for a fault
     */
    public function storeApiKey(string $key, bool $acknowledgedRisk, string $baseUrl): void
    {
        $this->assertNotInstalled();
        $owner = $this->owner('Create the owner before storing an API key.');

        $key = trim($key);

        if ($key === '') {
            throw new StorageException('The API key is empty.', self::ERR_EMPTY_KEY);
        }

        $result = $this->runSelfTest($baseUrl);

        if ($result->blocksApiKey() && !$acknowledgedRisk) {
            // The detail names only the public URL that was checked.
            throw new StorageException($result->detail, self::ERR_EXPOSED);
        }

        $this->storage->config()->set('api_key', $this->vault->encrypt($key));
        $this->storage->config()->set('api_key_algo', $this->vault->algorithm());

        $this->storage->auditLog()->record(AuditEvent::by(
            Principal::forUser($owner, Channel::BROWSER),
            AuditEvent::SELF_TEST,
            null,
            'data_dir',
            [
                'outcome'           => $result->reachable ? 'reachable' : ($result->inconclusive ? 'inconclusive' : 'not_reachable'),
                'reachable'         => $result->reachable,
                'inconclusive'      => $result->inconclusive,
                // Whether the owner stored the key over a warning.
                'acknowledged_risk' => $acknowledgedRisk,
            ]
        ));
    }

    /**
     * @throws StorageException coded ERR_ALREADY_INSTALLED or ERR_NO_OWNER for
     *         a refusal; uncoded for a fault
     */
    public function finalize(string $ip): void
    {
        $this->assertNotInstalled();
        $owner = $this->owner('Create the owner before finishing setup.');

        // installed_at is the commit point, so it is written last. Everything
        // an installed copy needs (the CSRF secret above all) exists before
        // it: a request that dies here leaves a copy that is not installed
        // and can be finished again, never one that is installed and cannot
        // sign anyone in.
        $config = $this->storage->config();
        // The installer does not ask about posture; every copy starts in emergency.
        $config->set('posture', 'emergency');
        $config->set('install_id', bin2hex(random_bytes(8)));
        $config->set('installed_at', (string) time());

        // The token has done its job; leaving it is one more secret to leak.
        // Best effort only: the installed_at check above is the gate.
        $tokenPath = $this->dataDir . '/' . self::TOKEN_FILE;
        if (is_file($tokenPath)) {
            @unlink($tokenPath);
        }

        $this->storage->auditLog()->record(AuditEvent::by(
            Principal::forUser($owner, Channel::BROWSER),
            AuditEvent::INSTALL,
            $ip,
            null,
            ['backend' => $this->storage->backend(), 'encryption' => $this->vault->algorithm()]
        ));
    }

    private function isInstalled(): bool
    {
        return $this->storage->config()->get('installed_at') !== null;
    }

    private function assertNotInstalled(): void
    {
        if ($this->isInstalled()) {
            throw new StorageException('This copy is already installed.', self::ERR_ALREADY_INSTALLED);
        }
    }

    private function owner(string $messageIfNone): User
    {
        $users = $this->storage->users()->all();

        if ($users === []) {
            throw new StorageException($messageIfNone, self::ERR_NO_OWNER);
        }

        return $users[0];
    }
}
