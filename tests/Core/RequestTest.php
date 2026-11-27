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

    /** @return array<string, array{0: string, 1: string}> folder as on disk, as in a URL */
    public static function foldersThatNeedEncoding(): array
    {
        return [
            'a space'     => ['wp safe mode', 'wp%20safe%20mode'],
            'parentheses' => ['wpsafemode (1)', 'wpsafemode%20%281%29'],
            'a digit'     => ['wpsafemode 2', 'wpsafemode%202'],
        ];
    }

    /** @dataProvider foldersThatNeedEncoding */
    public function testThePathIsFoundUnderAFolderWhoseNameNeedsEncoding(string $folder, string $encoded): void
    {
        // REQUEST_URI arrives percent-encoded; SCRIPT_NAME arrives decoded.
        $script = '/' . $folder . '/index.php';

        foreach ([
            '/' . $encoded . '/index.php/api/install/state' => '/api/install/state',
            '/' . $encoded . '/index.php/'                  => '/',
            '/' . $encoded . '/api/env?x=1'                 => '/api/env',
            '/' . $encoded . '/'                            => '/',
        ] as $uri => $path) {
            self::assertSame($path, (new Request('GET', $uri, $script))->path(), $uri);
        }
    }

    /** @dataProvider foldersThatNeedEncoding */
    public function testTheEncodedBasePathEncodesEachSegment(string $folder, string $encoded): void
    {
        $request = new Request('GET', '/', '/sub dir/' . $folder . '/index.php');

        self::assertSame('/sub dir/' . $folder, $request->basePath());
        self::assertSame('/sub%20dir/' . $encoded, $request->encodedBasePath());
        self::assertSame('', (new Request('GET', '/', '/index.php'))->encodedBasePath());
    }

    public function testBodyTypesAreCheckedOnlyForFieldsThatArePresent(): void
    {
        $request = new Request('POST', '/', '/index.php', (string) json_encode(['a' => 'x', 'b' => true, 'c' => 'false']));

        self::assertTrue($request->bodyHasTypes(['a' => 'string', 'b' => 'bool', 'missing' => 'string']));
        self::assertFalse($request->bodyHasTypes(['c' => 'bool']));
        self::assertFalse($request->bodyHasTypes(['b' => 'string']));
        self::assertFalse($request->bodyHasTypes(['a' => 'bool']));
    }
}
