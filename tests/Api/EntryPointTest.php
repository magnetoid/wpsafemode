<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\ErrorLog;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Tests\Support\ServedCopy;

/**
 * Drives the real src/index.php, served by php -S from a copy of src/ with
 * display_errors on, as a careless host would run it.
 */
final class EntryPointTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';
    private const SUBMITTED = 'the-submitted-password-7f3a';

    /** @var list<ServedCopy> */
    private array $copies = [];

    protected function tearDown(): void
    {
        foreach ($this->copies as $copy) {
            $copy->stop();
        }
    }

    private function serve(string $folder = 'wpsafemode'): ServedCopy
    {
        return $this->copies[] = ServedCopy::start($folder);
    }

    /** An installed copy with an owner, on the SQLite backend this host picks. */
    private function installed(ServedCopy $copy): string
    {
        $dataDir = $copy->appDir . '/data';
        $storage = StorageFactory::open($dataDir, Environment::probe($dataDir));
        $storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash(self::PASSWORD));
        $storage->config()->set('installed_at', '1');
        $storage->config()->set('install_id', 'abcdef0123456789');

        return $dataDir;
    }

    private function assertNoServerPath(string $body, ServedCopy $copy): void
    {
        self::assertStringNotContainsString($copy->base, $body);
        self::assertStringNotContainsString((string) realpath($copy->base), $body);
        self::assertSame(0, preg_match('#(^|[\s"\'(])/(Users|home|var|tmp|private|srv)/#', $body), 'A rooted path reached the browser: ' . $body);
    }

    public function testAnInternalFailureAnswersOnlyTheGenericJson(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Needs pdo_sqlite: the failure is forced through a read-only SQLite file.');
        }

        $copy = $this->serve();
        $dataDir = $this->installed($copy);
        $db = $dataDir . '/' . StorageFactory::sqliteFilename($dataDir);
        chmod($db, 0444);

        if (is_writable($db)) {
            self::markTestSkipped('This user can write a read-only file (root?), so the failure cannot be forced.');
        }

        // A wrong password reaches the rate limiter's write, which the
        // read-only database refuses with a raw PDOException.
        $response = $copy->request(
            'POST',
            $copy->target('index.php/api/auth/login'),
            (string) json_encode(['login' => 'owner', 'password' => self::SUBMITTED]),
            ['Content-Type' => 'application/json']
        );

        self::assertSame(500, $response['status']);
        self::assertSame('{"error":"server_error"}', $response['body']);
        self::assertStringStartsWith('application/json', $response['headers']['content-type'][0] ?? '');
        $this->assertNoServerPath($response['body'], $copy);
        self::assertStringNotContainsString(self::SUBMITTED, $response['body']);

        // The failure is logged behind the die guard, without the password.
        $log = (string) file_get_contents($dataDir . '/' . ErrorLog::FILE);
        self::assertStringStartsWith(DataDir::GUARD, $log);
        self::assertStringContainsString('PDOException', $log);
        self::assertStringNotContainsString(self::SUBMITTED, $log);
        self::assertStringNotContainsString(self::SUBMITTED, $copy->serverLog());
    }

    public function testAStorageFailureAnswersStorageUnavailableAndLogsOnlyTheClass(): void
    {
        $copy = $this->serve();
        // A file where the data directory belongs: it can never be created.
        file_put_contents($copy->appDir . '/data', 'not a directory');

        $response = $copy->request('GET', $copy->target('index.php/api/install/state'));

        self::assertSame(500, $response['status']);
        self::assertSame('{"error":"storage_unavailable"}', $response['body']);
        $this->assertNoServerPath($response['body'], $copy);

        // With no guarded log available, only the class name is written.
        $log = $copy->serverLog();
        self::assertStringContainsString('WP Safe Mode: WPSafeMode\\Storage\\Contracts\\StorageException', $log);
        self::assertStringNotContainsString('Cannot create data directory', $log);
        self::assertStringNotContainsString($copy->appDir, $log);
    }

    public function testTheShellTheAssetsAndTheApiCarryTheSecurityHeaders(): void
    {
        $copy = $this->serve();
        $asset = basename((string) (glob($copy->appDir . '/public/assets/*.js') ?: [''])[0]);
        self::assertNotSame('', $asset, 'The built frontend is missing from src/public/assets.');

        $answers = [
            'redirect' => $copy->request('GET', $copy->target()),
            'shell'    => $copy->request('GET', $copy->target('index.php/')),
            'asset'    => $copy->request('GET', $copy->target('index.php/assets/' . $asset)),
            'api'      => $copy->request('GET', $copy->target('index.php/api/install/state')),
            'api 404'  => $copy->request('GET', $copy->target('index.php/api/nope')),
        ];

        self::assertSame(302, $answers['redirect']['status']);
        self::assertSame(200, $answers['shell']['status']);
        self::assertSame(200, $answers['asset']['status']);
        self::assertSame(200, $answers['api']['status']);
        self::assertSame(404, $answers['api 404']['status']);

        foreach ($answers as $name => $answer) {
            self::assertSame(['no-referrer'], $answer['headers']['referrer-policy'] ?? null, $name);
            self::assertSame(['DENY'], $answer['headers']['x-frame-options'] ?? null, $name);
            self::assertSame(['nosniff'], $answer['headers']['x-content-type-options'] ?? null, $name);
        }
    }
}
