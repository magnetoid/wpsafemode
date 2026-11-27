<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\RouteMatch;
use WPSafeMode\Core\Router;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Permission;

final class RouterTest extends TestCase
{
    private function ok(): \Closure
    {
        return static fn (): Response => Response::json(['ok' => true]);
    }

    private function find(Router $router, Request $request): RouteMatch
    {
        return $router->match($request->method, $request->path());
    }

    public function testMatchesRegisteredRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/api/env', $this->ok(), Permission::OPEN);

        $match = $this->find($router, new Request('GET', '/api/env'));

        self::assertSame(RouteMatch::FOUND, $match->status);
        self::assertNotNull($match->route);
        self::assertSame('/api/env', $match->route->pattern);
        self::assertSame(Permission::OPEN, $match->route->permission);
        self::assertSame([Channel::BROWSER], $match->route->channels);
        self::assertFalse($match->route->preInstall);
        self::assertSame([], $match->params);
        self::assertSame('{"ok":true}', ($match->route->handler)(new Request('GET', '/api/env'))->body);
    }

    public function testMethodsAreMatchedCaseInsensitively(): void
    {
        $router = new Router();
        $router->add('get', '/api/env', $this->ok(), Permission::OPEN);

        self::assertSame(RouteMatch::FOUND, $router->match('GET', '/api/env')->status);
        self::assertSame(RouteMatch::FOUND, $router->match('get', '/api/env')->status);
    }

    public function testUnknownPathIsNotFound(): void
    {
        $match = $this->find(new Router(), new Request('GET', '/nope'));

        self::assertSame(RouteMatch::NOT_FOUND, $match->status);
        self::assertNull($match->route);
        self::assertSame([], $match->params);
    }

    public function testKnownPathWithWrongMethodIsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('POST', '/api/step', $this->ok(), Permission::OPEN);

        $match = $this->find($router, new Request('GET', '/api/step'));

        self::assertSame(RouteMatch::METHOD_NOT_ALLOWED, $match->status);
        self::assertNull($match->route);
        self::assertSame([], $match->params);
    }

    public function testQueryStringIsStrippedBeforeMatching(): void
    {
        $router = new Router();
        $router->add('GET', '/api/env', $this->ok(), Permission::OPEN);

        self::assertSame(RouteMatch::FOUND, $this->find($router, new Request('GET', '/api/env?cache=0'))->status);
    }

    public function testSubdirectoryInstallPrefixIsStripped(): void
    {
        // The tool lives in a randomly named directory (spec 5.2), so the
        // script prefix must not be part of route matching.
        $router = new Router();
        $router->add('GET', '/api/env', $this->ok(), Permission::OPEN);

        $request = new Request('GET', '/wpsm-a1b2c3/api/env', '/wpsm-a1b2c3/index.php');

        self::assertSame(RouteMatch::FOUND, $this->find($router, $request)->status);
    }

    public function testPathInfoUrlIsMatchedWhenTheHostHasNoRewriting(): void
    {
        // A drop-in ZIP cannot assume mod_rewrite or an .htaccess, so the tool
        // must also answer on the form every bare host supports:
        // /install-dir/index.php/api/env. Here the script name is part of the
        // request path, not just its parent directory.
        $router = new Router();
        $router->add('GET', '/api/env', $this->ok(), Permission::OPEN);

        $request = new Request('GET', '/wpsm-a1b2c3/index.php/api/env', '/wpsm-a1b2c3/index.php');

        self::assertSame(RouteMatch::FOUND, $this->find($router, $request)->status);
    }

    public function testPlaceholdersAreExtractedAndDecoded(): void
    {
        $router = new Router();
        $router->add('GET', '/api/sessions/{id}/steps/{seq}', $this->ok(), Permission::READ);

        $match = $router->match('GET', '/api/sessions/s%201/steps/4');

        self::assertSame(RouteMatch::FOUND, $match->status);
        self::assertSame(['id' => 's 1', 'seq' => '4'], $match->params);
    }

    public function testDecodedPlaceholdersKeepPlusAndEncodedSlashInsideOneSegment(): void
    {
        $router = new Router();
        $router->add('GET', '/api/sessions/{id}', $this->ok(), Permission::READ);

        self::assertSame(['id' => 'a+b'], $router->match('GET', '/api/sessions/a+b')->params);
        self::assertSame(['id' => 'a/b'], $router->match('GET', '/api/sessions/a%2Fb')->params);
    }

    public function testAStaticRouteWinsOverAPlaceholderRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/api/sessions/{id}', $this->ok(), Permission::READ);
        $router->add('GET', '/api/sessions/new', $this->ok(), Permission::OPERATE);

        $match = $router->match('GET', '/api/sessions/new');

        self::assertSame(RouteMatch::FOUND, $match->status);
        self::assertNotNull($match->route);
        self::assertSame('/api/sessions/new', $match->route->pattern);
        self::assertSame([], $match->params);
    }

    public function testAPlaceholderNeverSpansSegmentsOrMatchesEmpty(): void
    {
        $router = new Router();
        $router->add('GET', '/api/sessions/{id}', $this->ok(), Permission::READ);

        self::assertSame(RouteMatch::NOT_FOUND, $router->match('GET', '/api/sessions/a/b')->status);
        self::assertSame(RouteMatch::NOT_FOUND, $router->match('GET', '/api/sessions/')->status);
    }

    public function testAPlaceholderPathUnderAnotherMethodIsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('POST', '/api/sessions/{id}', $this->ok(), Permission::OPERATE);

        self::assertSame(RouteMatch::METHOD_NOT_ALLOWED, $router->match('GET', '/api/sessions/x')->status);
    }

    /** @dataProvider badRegistrations */
    public function testABadRegistrationIsRefused(string $permission, array $channels, bool $preInstall): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Router())->add('GET', '/x', $this->ok(), $permission, $channels, $preInstall);
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: bool}> */
    public static function badRegistrations(): array
    {
        return [
            'unknown permission' => ['nonsense', [Channel::BROWSER], false],
            'no channels'        => [Permission::READ, [], false],
            'unknown channel'    => [Permission::READ, ['carrier-pigeon'], false],
            'owner on remote'    => [Permission::OWN, [Channel::BROWSER, Channel::REMOTE], false],
            'pre-install needs open' => [Permission::READ, [Channel::BROWSER], true],
        ];
    }

    public function testRoutesAreListedInRegistrationOrder(): void
    {
        $router = new Router();
        $router->add('GET', '/b', $this->ok(), Permission::OPEN);
        $router->add('POST', '/a', $this->ok(), Permission::READ);
        $router->add('GET', '/c/{x}', $this->ok(), Permission::OPEN, [Channel::BROWSER, Channel::REMOTE], true);

        $routes = $router->routes();

        self::assertCount(3, $routes);
        self::assertSame(['/b', '/a', '/c/{x}'], array_map(static fn ($r): string => $r->pattern, $routes));
        self::assertSame('POST', $routes[1]->method);
        self::assertTrue($routes[2]->preInstall);
    }

    public function testBareScriptUrlResolvesToRoot(): void
    {
        $request = new Request('GET', '/wpsm-a1b2c3/index.php', '/wpsm-a1b2c3/index.php');

        self::assertSame('/', $request->path());
    }

    public function testPrefixIsOnlyStrippedOnASegmentBoundary(): void
    {
        // /index.phpsomething must not be mistaken for /index.php + /something.
        $request = new Request('GET', '/index.phpsomething', '/index.php');

        self::assertSame('/index.phpsomething', $request->path());
    }
}
