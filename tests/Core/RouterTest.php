<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\Router;

final class RouterTest extends TestCase
{
    public function testDispatchesRegisteredRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/api/env', static fn (): Response => Response::json(['ok' => true]));

        $response = $router->dispatch(new Request('GET', '/api/env'));

        self::assertSame(200, $response->status);
        self::assertSame('{"ok":true}', $response->body);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testUnknownPathIsNotFound(): void
    {
        self::assertSame(404, (new Router())->dispatch(new Request('GET', '/nope'))->status);
    }

    public function testKnownPathWithWrongMethodIsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('POST', '/api/step', static fn (): Response => Response::json([]));

        self::assertSame(405, $router->dispatch(new Request('GET', '/api/step'))->status);
    }

    public function testQueryStringIsStrippedBeforeMatching(): void
    {
        $router = new Router();
        $router->add('GET', '/api/env', static fn (): Response => Response::json(['ok' => true]));

        self::assertSame(200, $router->dispatch(new Request('GET', '/api/env?cache=0'))->status);
    }

    public function testSubdirectoryInstallPrefixIsStripped(): void
    {
        // The tool lives in a randomly named directory (spec 5.2), so the
        // script prefix must not be part of route matching.
        $router = new Router();
        $router->add('GET', '/api/env', static fn (): Response => Response::json(['ok' => true]));

        $request = new Request('GET', '/wpsm-a1b2c3/api/env', '/wpsm-a1b2c3/index.php');

        self::assertSame(200, $router->dispatch($request)->status);
    }

    public function testPathInfoUrlIsMatchedWhenTheHostHasNoRewriting(): void
    {
        // A drop-in ZIP cannot assume mod_rewrite or an .htaccess, so the tool
        // must also answer on the form every bare host supports:
        // /install-dir/index.php/api/env. Here the script name is part of the
        // request path, not just its parent directory.
        $router = new Router();
        $router->add('GET', '/api/env', static fn (): Response => Response::json(['ok' => true]));

        $request = new Request('GET', '/wpsm-a1b2c3/index.php/api/env', '/wpsm-a1b2c3/index.php');

        self::assertSame(200, $router->dispatch($request)->status);
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
