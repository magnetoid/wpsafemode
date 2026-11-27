<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\RouteMatch;
use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\Permission;
use WPSafeMode\Security\Principal;
use WPSafeMode\Storage\Contracts\Storage;

/**
 * The one request path. Every check runs here, in a fixed order, before any
 * handler: routing, the install gate, who is calling, channel, permission,
 * CSRF. A handler that runs has already been authorised.
 *
 * Storage errors are deliberately not caught: the composition root answers
 * them, and a half-answered request must never fall through to a handler.
 */
final class Kernel
{
    public function __construct(
        private Router $router,
        private Storage $storage,
        private AuthSessions $sessions,
        private Installer $installer,
    ) {
    }

    public function handle(Request $request, int $now): Response
    {
        // 1. Routing.
        $match = $this->router->match($request->method, $request->path());

        if ($match->status === RouteMatch::METHOD_NOT_ALLOWED) {
            return Response::methodNotAllowed();
        }

        if ($match->status !== RouteMatch::FOUND || $match->route === null) {
            return Response::notFound();
        }

        $route = $match->route;
        $state = $this->installer->state();

        // 2. Nothing but the installer answers before install.
        if (!$state->installed && !$route->preInstall) {
            return Response::json(['error' => 'not_installed', 'step' => $state->step], 409);
        }

        // 3. The installer's mutations are over once installed; its reads stay.
        if ($state->installed && $route->preInstall && $request->method !== 'GET') {
            return Response::json(['error' => 'already_installed'], 409);
        }

        // 4. Who is calling.
        $sessionToken = $request->cookie(AuthSessions::COOKIE);
        $user = $sessionToken === null || $sessionToken === ''
            ? null
            : $this->sessions->validate($sessionToken, $request->userAgent(), $now);
        $principal = $user === null
            ? Principal::anonymous(Channel::BROWSER)
            : Principal::forUser($user, Channel::BROWSER);

        // 5. Channel.
        if (!in_array($principal->channel(), $route->channels, true)) {
            return Response::json(['error' => 'channel_not_allowed'], 403);
        }

        // 6. Permission.
        if ($route->permission !== Permission::OPEN && !$principal->can($route->permission)) {
            return $principal->isAnonymous()
                ? Response::json(['error' => 'unauthenticated'], 401)
                : Response::json(['error' => 'forbidden'], 403);
        }

        // 7. CSRF, for a logged-in browser that is changing something. An
        // anonymous caller has no session to forge, which is what lets login
        // itself be a POST.
        if (
            !$principal->isAnonymous()
            && $principal->channel() === Channel::BROWSER
            && !in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)
        ) {
            $secret = $this->storage->config()->get('install_id');

            // Fail closed: an empty secret would make every token forgeable.
            if (
                $secret === null || $secret === ''
                || $sessionToken === null
                || !Csrf::verify($sessionToken, $secret, $request->header(Csrf::HEADER) ?? '')
            ) {
                return Response::json(['error' => 'bad_csrf'], 403);
            }
        }

        // 8. Hand over.
        $request->principal = $principal;
        $request->params = $match->params;

        return ($route->handler)($request);
    }
}
