<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\InstallController;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Tests\Support\FailingStorage;
use WPSafeMode\Tests\Support\FakeFetcher;

final class InstallControllerTest extends TestCase
{
    private const STRONG = 'correct horse battery staple';

    private string $dir;
    private Storage $storage;
    private Installer $installer;
    private InstallController $controller;
    private string $token;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-installctl-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->storage = StorageFactory::create($this->dir, 'json');
        $this->installer = new Installer(
            $this->storage,
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($this->dir),
            // Every self-test request fails: inconclusive, and no network.
            FakeFetcher::unreachable()
        );
        $this->controller = new InstallController($this->installer, $this->dir . '/never-renamed');
        $this->token = $this->installer->issueToken();
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }
        rmdir($path);
    }

    /** @param array<string, mixed> $body */
    private function request(array $body, string $host = 'example.test'): Request
    {
        return new Request(
            'POST',
            '/api/install',
            '/index.php',
            (string) json_encode($body),
            ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_HOST' => $host]
        );
    }

    /** @return array<string, mixed> */
    private function decode(\WPSafeMode\Core\Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @return array<string, array{0: string}> */
    public static function tokenGuardedActions(): array
    {
        return [
            'createOwner' => ['createOwner'],
            'selfTest'    => ['selfTest'],
            'apiKey'      => ['apiKey'],
            'finish'      => ['finish'],
        ];
    }

    /** @dataProvider tokenGuardedActions */
    public function testInstallerMutationsRequireTheToken(string $action): void
    {
        // An owner exists, so the action could otherwise succeed (or at least
        // fail for a reason other than the token).
        $this->installer->createOwner('owner', self::STRONG);
        $payload = ['login' => 'second', 'password' => self::STRONG, 'api_key' => 'sk-ant-key', 'acknowledged_risk' => true];

        foreach ([$payload, $payload + ['token' => 'WRONG']] as $body) {
            $response = $this->controller->$action($this->request($body));

            self::assertSame(403, $response->status);
            self::assertSame(['error' => 'bad_token'], $this->decode($response));
        }

        self::assertCount(1, $this->storage->users()->all());
        self::assertFalse($this->storage->config()->has('api_key'));
        self::assertNull($this->storage->config()->get('installed_at'));
        self::assertFalse($this->installer->state()->installed);
        self::assertNull($this->installer->state()->selfTest);
        self::assertSame([], glob($this->dir . '/canary-*.txt') ?: []);
    }

    public function testSelfTestAnswersNoBaseUrlForAMalformedHost(): void
    {
        $response = $this->controller->selfTest($this->request(['token' => $this->token], 'evil.com/x?'));

        self::assertSame(409, $response->status);
        self::assertSame(['error' => 'no_base_url'], $this->decode($response));
    }

    public function testStateIsFullBeforeInstallAndMinimalAfter(): void
    {
        $before = $this->decode($this->controller->state($this->request([])));

        foreach (['has_owner', 'has_api_key', 'encryption', 'has_wordpress'] as $key) {
            self::assertArrayHasKey($key, $before);
        }

        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->finalize('203.0.113.9');

        self::assertSame(
            ['installed' => true, 'step' => 'done'],
            $this->decode($this->controller->state($this->request([])))
        );
    }

    public function testTheFirstStateRequestIssuesTheToken(): void
    {
        // A fresh copy: setUp issued a token, so start from none.
        $file = $this->dir . '/install-token.php';
        unlink($file);

        $first = $this->decode($this->controller->state($this->request([])));

        self::assertSame('token', $first['step']);
        self::assertFileExists($file);
        $issued = GuardedJson::read($file)['token'] ?? null;
        self::assertIsString($issued);

        $this->controller->state($this->request([]));
        self::assertSame($issued, GuardedJson::read($file)['token'] ?? null);

        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->finalize('203.0.113.9');
        self::assertFileDoesNotExist($file);

        $this->controller->state($this->request([]));
        self::assertFileDoesNotExist($file);
    }

    public function testALostTokenIsIssuedAgainWhileAnOwnerExists(): void
    {
        $file = $this->dir . '/install-token.php';
        $this->installer->createOwner('owner', self::STRONG);
        unlink($file);

        $body = $this->decode($this->controller->state($this->request([])));

        self::assertFileExists($file);
        self::assertSame('api_key', $body['step']);
        self::assertTrue($this->installer->verifyToken((string) (GuardedJson::read($file)['token'] ?? '')));
    }

    public function testAWeakPasswordListsEveryWeakness(): void
    {
        $response = $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'owner', 'password' => 'short'])
        );
        $body = $this->decode($response);

        self::assertSame(422, $response->status);
        self::assertSame('weak_password', $body['error']);
        self::assertSame(implode(' ', PasswordHasher::weaknesses('short')), $body['message']);
        self::assertTrue($this->storage->users()->isEmpty());
    }

    public function testCreateOwnerMapsBadLoginAndOwnerExists(): void
    {
        $bad = $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'a b', 'password' => self::STRONG])
        );
        self::assertSame(422, $bad->status);
        self::assertSame('bad_login', $this->decode($bad)['error']);

        $ok = $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'owner', 'password' => self::STRONG])
        );
        self::assertSame(200, $ok->status);

        $again = $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'second', 'password' => self::STRONG])
        );
        self::assertSame(409, $again->status);
        self::assertSame('owner_exists', $this->decode($again)['error']);
    }

    public function testTheWizardCompletesThroughTheController(): void
    {
        $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'owner', 'password' => self::STRONG])
        );

        // No reachable self-test target: inconclusive, so the key needs the acknowledgement.
        $refused = $this->controller->apiKey($this->request(['token' => $this->token, 'api_key' => 'sk-ant-key']));
        self::assertSame(422, $refused->status);

        $stored = $this->controller->apiKey(
            $this->request(['token' => $this->token, 'api_key' => 'sk-ant-key', 'acknowledged_risk' => true])
        );
        self::assertSame(200, $stored->status);

        $done = $this->controller->finish($this->request(['token' => $this->token]));
        self::assertSame(200, $done->status);
        self::assertTrue($this->installer->state()->installed);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function malformedBodies(): array
    {
        return [
            'token as a number'          => ['verifyToken', ['token' => 5]],
            'login as an array'          => ['createOwner', ['login' => ['a'], 'password' => self::STRONG]],
            'password as an array'       => ['createOwner', ['login' => 'owner', 'password' => [self::STRONG]]],
            'api_key as a number'        => ['apiKey', ['api_key' => 12345, 'acknowledged_risk' => true]],
            'acknowledged_risk "false"'  => ['apiKey', ['api_key' => 'sk-ant-key', 'acknowledged_risk' => 'false']],
            'acknowledged_risk 1'        => ['apiKey', ['api_key' => 'sk-ant-key', 'acknowledged_risk' => 1]],
            'name as an array'           => ['rename', ['name' => ['wpsm-abcabcabc']]],
            'token as an array (finish)' => ['finish', ['token' => ['x']]],
        ];
    }

    /**
     * @dataProvider malformedBodies
     * @param array<string, mixed> $body
     */
    public function testABodyFieldOfTheWrongTypeIsABadRequest(string $action, array $body): void
    {
        if ($action === 'apiKey') {
            $this->installer->createOwner('owner', self::STRONG);
        }

        $before = count($this->storage->users()->all());
        $response = $this->controller->$action($this->request($body + ['token' => $this->token]));

        self::assertSame(422, $response->status);
        self::assertSame(['error' => 'bad_request'], $this->decode($response));
        self::assertCount($before, $this->storage->users()->all());
        self::assertFalse($this->storage->config()->has('api_key'));
        self::assertFalse($this->installer->state()->installed);
    }

    /** @return array<string, array{0: string}> */
    public static function faultyWrites(): array
    {
        return ['api key' => ['apiKey'], 'finish' => ['finish']];
    }

    /**
     * A storage fault is not a refusal the user can act on: it is rethrown,
     * so the composition root answers the generic 500 with no path in it.
     *
     * @dataProvider faultyWrites
     */
    public function testAStorageFaultIsRethrownNotReportedAsARefusal(string $action): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $failing = new FailingStorage($this->storage, static fn (): bool => true);
        $installer = new Installer($failing, new KeyVault($this->dir), new PasswordHasher(), $this->dir, Environment::probe($this->dir), FakeFetcher::unreachable());
        $controller = new InstallController($installer, $this->dir . '/never-renamed');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(FailingStorage::MESSAGE);

        $controller->$action($this->request(['token' => $this->token, 'api_key' => 'sk-ant-key', 'acknowledged_risk' => true]));
    }

    public function testRefusalsAreStill422WithAPathFreeMessage(): void
    {
        $noOwner = $this->controller->finish($this->request(['token' => $this->token]));
        self::assertSame(422, $noOwner->status);
        self::assertSame('incomplete', $this->decode($noOwner)['error']);

        $this->installer->createOwner('owner', self::STRONG);

        foreach ([
            ['api_key' => '   ', 'acknowledged_risk' => true],
            ['api_key' => 'sk-ant-key', 'acknowledged_risk' => false],
            ['api_key' => 'sk-ant-key'],
        ] as $body) {
            $response = $this->controller->apiKey($this->request($body + ['token' => $this->token]));

            self::assertSame(422, $response->status);
            self::assertSame('refused', $this->decode($response)['error']);
            self::assertStringNotContainsString($this->dir, $response->body);
        }

        self::assertFalse($this->storage->config()->has('api_key'));
    }

    public function testAWeakPasswordAnswersTheListOfWeaknesses(): void
    {
        $password = "password\0";
        $response = $this->controller->createOwner(
            $this->request(['token' => $this->token, 'login' => 'owner', 'password' => $password])
        );
        $body = $this->decode($response);

        self::assertSame(422, $response->status);
        self::assertSame(PasswordHasher::weaknesses($password), $body['weaknesses']);
        self::assertGreaterThanOrEqual(3, count($body['weaknesses']));
    }

    public function testStateNeverAnswersTheInstallToken(): void
    {
        $answers = [$this->controller->state($this->request([]))->body];

        $this->installer->createOwner('owner', self::STRONG);
        $answers[] = $this->controller->state($this->request([]))->body;

        // A lost token is written again while an owner exists: still not sent.
        unlink($this->dir . '/install-token.php');
        $answers[] = $this->controller->state($this->request([]))->body;
        $reissued = (string) (GuardedJson::read($this->dir . '/install-token.php')['token'] ?? '');
        self::assertNotSame('', $reissued);

        $this->installer->finalize('203.0.113.9');
        $answers[] = $this->controller->state($this->request([]))->body;

        foreach ($answers as $i => $body) {
            self::assertStringNotContainsString($this->token, $body, 'state answer ' . $i);
            self::assertStringNotContainsString($reissued, $body, 'state answer ' . $i);
            self::assertArrayNotHasKey('token', (array) json_decode($body, true), 'state answer ' . $i);
        }
    }
}
