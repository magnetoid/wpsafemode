<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\SelfTestHttp;
use WPSafeMode\Security\SelfTestResult;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Tests\Support\FakeFetcher;
use WPSafeMode\Tests\Support\ServedCopy;

final class WebAccessibilitySelfTestTest extends TestCase
{
    private const BASE = 'http://127.0.0.1:9/wpsafemode';

    private string $dir;

    /** @var list<ServedCopy> */
    private array $servers = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-selftest-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function server(string $router): ServedCopy
    {
        return $this->servers[] = ServedCopy::router($router);
    }

    /** @return list<string> */
    private function canaries(): array
    {
        return array_values(array_filter(
            glob($this->dir . '/*') ?: [],
            static fn (string $p): bool => strpos(basename($p), 'canary') === 0
        ));
    }

    /** @return array<string, array{0: array{status: int, body: string}|null, 1: array{status: int, body: string}|null}> */
    public static function controlFailures(): array
    {
        $html = ['status' => 200, 'body' => '<html>Welcome to nginx!</html>'];

        return [
            'WAF 403, challenge page'      => [['status' => 403, 'body' => 'Forbidden'], ['status' => 403, 'body' => 'challenge']],
            '404, default vhost'           => [['status' => 404, 'body' => ''], ['status' => 404, 'body' => '']],
            '404, HTML instead of JSON'    => [['status' => 404, 'body' => ''], $html],
            '410, JSON without installed'  => [['status' => 410, 'body' => ''], ['status' => 200, 'body' => '{"ok":true}']],
            '401, installed not a boolean' => [['status' => 401, 'body' => ''], ['status' => 200, 'body' => '{"installed":"false"}']],
            '200 other body, JSON list'    => [['status' => 200, 'body' => 'x'], ['status' => 200, 'body' => '[true]']],
            '404, control redirected'      => [['status' => 404, 'body' => ''], ['status' => 302, 'body' => '{"installed":false}']],
            '404, control failed'          => [['status' => 404, 'body' => ''], ['status' => 0, 'body' => '']],
            '404, control 500'             => [['status' => 404, 'body' => ''], ['status' => 500, 'body' => '{"installed":false}']],
        ];
    }

    /**
     * @dataProvider controlFailures
     * @param array{status: int, body: string} $canary
     * @param array{status: int, body: string} $control
     */
    public function testARefusalFromAnythingButThisToolIsInconclusive(array $canary, array $control): void
    {
        $fetcher = FakeFetcher::answering($canary, $control);

        $result = WebAccessibilitySelfTest::run($this->dir, self::BASE, $fetcher);

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
        self::assertTrue($result->blocksApiKey());
        self::assertSame(self::BASE . WebAccessibilitySelfTest::CONTROL_PATH, $fetcher->requested[1] ?? null);
        self::assertSame([], $this->canaries());
    }

    public function testAControlTheTransportCannotMakeIsInconclusive(): void
    {
        $result = WebAccessibilitySelfTest::run($this->dir, self::BASE, FakeFetcher::answering(['status' => 404, 'body' => ''], null, false));

        self::assertTrue($result->inconclusive);
    }

    public function testARefusalConfirmedByThisToolsOwnAnswerPasses(): void
    {
        foreach ([401, 403, 404, 410] as $status) {
            $fetcher = FakeFetcher::answering(['status' => $status, 'body' => 'no']);

            $result = WebAccessibilitySelfTest::run($this->dir, self::BASE . '/', $fetcher);

            self::assertFalse($result->reachable, (string) $status);
            self::assertFalse($result->inconclusive, (string) $status);
            self::assertFalse($result->blocksApiKey(), (string) $status);
            self::assertCount(2, $fetcher->requested);
            self::assertStringStartsWith(self::BASE . '/data/canary-', $fetcher->requested[0]);
            self::assertSame(self::BASE . '/index.php/api/install/state', $fetcher->requested[1]);
        }
    }

    public function testAServedCanaryIsReachableWithoutAskingForTheControl(): void
    {
        $fetcher = FakeFetcher::serving($this->dir);

        $result = WebAccessibilitySelfTest::run($this->dir, self::BASE, $fetcher);

        self::assertTrue($result->reachable);
        self::assertFalse($result->inconclusive);
        self::assertTrue($result->blocksApiKey());
        self::assertCount(1, $fetcher->requested);
        self::assertSame([], $this->canaries());
    }

    /** @return array<string, array{0: array{status: int, body: string}|null}> */
    public static function undecidedCanaries(): array
    {
        return [
            'transport failure' => [['status' => 0, 'body' => '']],
            'redirect'          => [['status' => 302, 'body' => '']],
            'server error'      => [['status' => 500, 'body' => '']],
            'rate limited'      => [['status' => 429, 'body' => '']],
            'no HTTP client'    => [null],
        ];
    }

    /**
     * @dataProvider undecidedCanaries
     * @param array{status: int, body: string}|null $canary
     */
    public function testAnUndecidedCanaryIsInconclusiveWithoutTheControl(?array $canary): void
    {
        $fetcher = FakeFetcher::answering($canary);

        $result = WebAccessibilitySelfTest::run($this->dir, self::BASE, $fetcher);

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
        self::assertCount(1, $fetcher->requested);
    }

    public function testNoBaseUrlIsInconclusiveWithoutAnyRequest(): void
    {
        $fetcher = FakeFetcher::refusing();

        $result = WebAccessibilitySelfTest::run($this->dir, null, $fetcher);

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
        self::assertNull($result->checkedUrl);
        self::assertSame([], $fetcher->requested);
        self::assertSame([], $this->canaries());
    }

    public function testTheCanaryIsRemovedEvenWhenTheTransportThrows(): void
    {
        $fetcher = new FakeFetcher(static function (): array {
            throw new \RuntimeException('transport exploded');
        });

        try {
            WebAccessibilitySelfTest::run($this->dir, self::BASE, $fetcher);
            self::fail('The exception was swallowed');
        } catch (\RuntimeException $e) {
            self::assertSame('transport exploded', $e->getMessage());
        }

        self::assertSame([], $this->canaries());
    }

    /**
     * Against a real server that is not this tool: everything answers 404,
     * as a default virtual host or a WAF would. Before the control, that
     * read as "not readable".
     */
    public function testARealServerThatIsNotThisToolCannotPassTheCheck(): void
    {
        $server = $this->server('<?php http_response_code(404); echo "Not Found";');

        $result = WebAccessibilitySelfTest::run($this->dir, $server->url('/wpsafemode'));

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
    }

    /** The real transport, end to end, against a served copy of the tool. */
    public function testTheRealTransportPassesOnlyWhenThisToolAnswersTheControl(): void
    {
        $copy = ServedCopy::start();
        $this->servers[] = $copy;
        // The first request creates data/ with its guard index.php, which is
        // what php -S falls back to (and answers 404 from) for a missing file.
        self::assertSame(200, $copy->request('GET', $copy->target('index.php/api/install/state'))['status']);

        // The canary goes somewhere the server does not serve: refused, and
        // the control reaches the copy's own state route.
        $refused = WebAccessibilitySelfTest::run($this->dir, $copy->url('/wpsafemode'));
        self::assertFalse($refused->reachable);
        self::assertFalse($refused->inconclusive, $refused->detail);

        // php -S serves data/*.txt statically: reachable.
        $exposed = WebAccessibilitySelfTest::run($copy->appDir . '/data', $copy->url('/wpsafemode'));
        self::assertTrue($exposed->reachable);
    }

    private const TRANSPORT_FIXTURE = <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/redirect') {
    header('Location: /target', true, 302);
    echo 'redirect body';
    return;
}
if ($path === '/target') {
    echo 'followed';
    return;
}
if ($path === '/ua') {
    echo $_SERVER['HTTP_USER_AGENT'] ?? '(none)';
    return;
}
http_response_code(404);
PHP;

    public function testTheStreamFallbackSendsTheUserAgentAndNeverFollowsARedirect(): void
    {
        $server = $this->server(self::TRANSPORT_FIXTURE);

        self::assertSame(['status' => 200, 'body' => SelfTestHttp::USER_AGENT], SelfTestHttp::viaStream($server->url('/ua')));

        $redirect = SelfTestHttp::viaStream($server->url('/redirect'));
        self::assertSame(302, $redirect['status']);
        self::assertStringNotContainsString('followed', $redirect['body']);

        self::assertSame(404, SelfTestHttp::viaStream($server->url('/missing'))['status']);
        self::assertSame(['status' => 0, 'body' => ''], SelfTestHttp::viaStream('http://127.0.0.1:1/x'));
    }

    public function testARedirectOnTheStreamFallbackEndsInconclusive(): void
    {
        // Every path under /redirect answers 302 to /target.
        $server = $this->server(str_replace("\$path === '/redirect'", "strpos(\$path, '/redirect') === 0", self::TRANSPORT_FIXTURE));
        $stream = new FakeFetcher(static fn (string $url): array => SelfTestHttp::viaStream($url));

        $result = WebAccessibilitySelfTest::run($this->dir, $server->url('/redirect'), $stream);

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
        self::assertCount(1, $stream->requested, 'The redirect was followed or the control was asked for');
    }

    public function testCurlSendsTheUserAgentAndNeverFollowsARedirect(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('cURL is not available.');
        }

        $server = $this->server(self::TRANSPORT_FIXTURE);

        self::assertSame(['status' => 200, 'body' => SelfTestHttp::USER_AGENT], SelfTestHttp::viaCurl($server->url('/ua')));

        $redirect = SelfTestHttp::viaCurl($server->url('/redirect'));
        self::assertNotNull($redirect);
        self::assertSame(302, $redirect['status']);
        self::assertStringNotContainsString('followed', $redirect['body']);
    }

    public function testAConnectionFailureIsInconclusiveAndBlocksTheApiKey(): void
    {
        // Port 1 on localhost refuses immediately. A server that cannot reach
        // its own site has not proven the directory is protected.
        $result = WebAccessibilitySelfTest::run($this->dir, 'http://127.0.0.1:1/wpsm');

        self::assertFalse($result->reachable);
        self::assertTrue($result->inconclusive);
        self::assertTrue($result->blocksApiKey());
        self::assertSame([], $this->canaries(), 'A canary file was left in the data directory');
    }

    public function testAReachableCanaryReportsReachableAndBlocksTheApiKey(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL (and WPSM_SELFTEST_DATA_DIR) to a base URL serving the data dir; see Step 4.');
        }

        // The canary must be written where the server actually serves /data/.
        $served = getenv('WPSM_SELFTEST_DATA_DIR');
        $dataDir = ($served === false || $served === '') ? $this->dir : $served;

        $result = WebAccessibilitySelfTest::run($dataDir, $server);

        self::assertTrue($result->reachable);
        self::assertTrue($result->blocksApiKey());
    }

    public function testAnInconclusiveResultAlsoBlocksTheApiKey(): void
    {
        // "We could not check" must fail closed. An installer that stores the
        // key when the check did not run is the v1.1 failure with extra steps.
        $result = new SelfTestResult(false, true, 'cURL is unavailable', null, 1000);

        self::assertTrue($result->blocksApiKey());
    }

    public function testBaseUrlIsDerivedFromTheRequestWithoutTheScriptName(): void
    {
        $url = WebAccessibilitySelfTest::baseUrlFrom([
            'HTTPS'       => 'on',
            'HTTP_HOST'   => 'example.com',
            'SCRIPT_NAME' => '/wpsm-a1b2c3/index.php',
        ]);

        self::assertSame('https://example.com/wpsm-a1b2c3', $url);
    }

    public function testBaseUrlEncodesAFolderNameThatNeedsIt(): void
    {
        $url = WebAccessibilitySelfTest::baseUrlFrom([
            'HTTP_HOST'   => 'example.com',
            'SCRIPT_NAME' => '/sites/wp safe mode (1)/index.php',
        ]);

        self::assertSame('http://example.com/sites/wp%20safe%20mode%20%281%29', $url);
    }

    public function testAServedButMissingCanaryReportsNotReachable(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL to a running server that does not serve the data dir.');
        }

        // The temp dir is not under the server's docroot, so the server answers 404.
        $result = WebAccessibilitySelfTest::run($this->dir, $server);

        self::assertFalse($result->reachable);
        self::assertFalse($result->inconclusive);
        self::assertFalse($result->blocksApiKey());
    }

    /** @dataProvider badHosts */
    public function testBaseUrlRefusesAMalformedHost(string $host): void
    {
        self::assertNull(WebAccessibilitySelfTest::baseUrlFrom(['HTTP_HOST' => $host, 'SCRIPT_NAME' => '/index.php']));
    }

    /** @return array<string, array{string}> */
    public function badHosts(): array
    {
        return [
            'path and query' => ['10.0.0.5:8080/admin?'],
            'fragment'       => ['evil#'],
            'space'          => ['a b'],
            'header inject'  => ["host\r\nX: y"],
            'empty'          => [''],
            'userinfo'       => ['user@host'],
            'trailing nl'    => ["example.com\n"],
        ];
    }

    /** @dataProvider goodHosts */
    public function testBaseUrlAcceptsAPlainHost(string $host): void
    {
        self::assertSame(
            'http://' . $host,
            WebAccessibilitySelfTest::baseUrlFrom(['HTTP_HOST' => $host, 'SCRIPT_NAME' => '/index.php'])
        );
    }

    /** @return array<string, array{string}> */
    public function goodHosts(): array
    {
        return [
            'domain'    => ['example.com'],
            'port'      => ['example.com:8443'],
            'ipv4 port' => ['127.0.0.1:8125'],
            'ipv6 port' => ['[::1]:8080'],
        ];
    }

    public function testBaseUrlIsNullWhenTheHostIsUnknown(): void
    {
        self::assertNull(WebAccessibilitySelfTest::baseUrlFrom(['SCRIPT_NAME' => '/index.php']));
    }
}
