<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Request;

final class RequestTest extends TestCase
{
    /** @param array<string, mixed> $server */
    private function request(array $server): Request
    {
        return new Request('GET', '/', '/index.php', '', $server);
    }

    public function testHeadersAreReadCaseInsensitively(): void
    {
        $request = $this->request(['HTTP_X_WPSM_CSRF' => 'abc', 'CONTENT_TYPE' => 'application/json']);

        self::assertSame('abc', $request->header('x-wpsm-csrf'));
        self::assertSame('application/json', $request->header('Content-Type'));
        self::assertSame('application/json', $request->header('content-type'));
        self::assertNull($request->header('X-Missing'));
    }

    public function testCookiesAreParsedFromTheHeader(): void
    {
        $request = $this->request(['HTTP_COOKIE' => 'a=1; wpsm_session=tok-en; b=2']);

        self::assertSame('tok-en', $request->cookie('wpsm_session'));
        self::assertNull($request->cookie('none'));
    }

    public function testTheFirstCookieOfANameWinsAndPairsWithoutEqualsAreIgnored(): void
    {
        $request = $this->request(['HTTP_COOKIE' => 'junk; a=first; a=second; b=x=y']);

        self::assertSame('first', $request->cookie('a'));
        self::assertSame('x=y', $request->cookie('b'));
        self::assertNull($request->cookie('junk'));
    }

    public function testClientIpIsTheDirectPeerAndIgnoresForwardedHeaders(): void
    {
        $request = $this->request(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '10.0.0.1']);

        self::assertSame('203.0.113.9', $request->clientIp());
    }

    public function testClientIpFallsBackToAPlaceholder(): void
    {
        self::assertSame('unknown', $this->request([])->clientIp());
    }

    public function testHttpsIsDetectedFromBothTheServerVarAndTheProxyHeader(): void
    {
        self::assertTrue($this->request(['HTTPS' => 'on'])->isHttps());
        self::assertFalse($this->request(['HTTPS' => 'off'])->isHttps());
        self::assertTrue($this->request(['HTTP_X_FORWARDED_PROTO' => 'https'])->isHttps());
        self::assertFalse($this->request([])->isHttps());
    }

    public function testUserAgentDefaultsToEmpty(): void
    {
        self::assertSame('', $this->request([])->userAgent());
        self::assertSame('curl/8', $this->request(['HTTP_USER_AGENT' => 'curl/8'])->userAgent());
    }

    public function testBasePathIsTheInstallDirectory(): void
    {
        self::assertSame('/wpsm-ab12', (new Request('GET', '/', '/wpsm-ab12/index.php'))->basePath());
        self::assertSame('', (new Request('GET', '/', '/index.php'))->basePath());
    }
}
