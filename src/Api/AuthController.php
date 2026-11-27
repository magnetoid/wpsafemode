<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\Principal;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\User;

final class AuthController
{
    public function __construct(
        private Storage $storage,
        private AuthSessions $sessions,
        private Installer $installer,
    ) {
    }

    public function login(Request $request): Response
    {
        if (!$request->bodyHasTypes(['login' => 'string', 'password' => 'string'])) {
            return Response::badRequest();
        }

        $secret = $this->csrfSecret();

        if ($secret === null) {
            return $this->corrupt();
        }

        $body = $request->json();
        $login = $body['login'] ?? '';
        $password = $body['password'] ?? '';
        $now = time();

        $result = $this->sessions->login($login, $password, $request->clientIp(), $request->userAgent(), $now);

        if (!$result->ok || $result->token === null || $result->userId === null) {
            $reason = $result->reason ?? 'bad_credentials';

            // The attempted login name is never recorded: people paste a
            // password into the login box, and an audit line would keep it.
            $this->storage->auditLog()->record(AuditEvent::by(
                Principal::anonymous(Channel::BROWSER),
                AuditEvent::LOGIN_FAILED,
                $request->clientIp(),
                null,
                ['reason' => $reason]
            ));

            switch ($reason) {
                case 'locked_out':
                    return Response::json(['error' => 'locked_out', 'retry_after' => $result->retryAfter], 429);
                case 'not_installed':
                    return Response::json(['error' => 'not_installed'], 409);
                default:
                    return Response::json(['error' => 'bad_credentials', 'retry_after' => $result->retryAfter], 401);
            }
        }

        $user = $this->storage->users()->find($result->userId);

        if ($user === null) {
            return Response::json(['error' => 'bad_credentials', 'retry_after' => 0], 401);
        }

        $this->storage->auditLog()->record(AuditEvent::by(
            Principal::forUser($user, Channel::BROWSER),
            AuditEvent::LOGIN,
            $request->clientIp()
        ));

        // Only a successful login runs the self-test. An undeterminable
        // address gives an inconclusive result without a probe, which is the
        // honest answer.
        $selfTest = $this->installer->runSelfTest(WebAccessibilitySelfTest::baseUrlFrom($request->server));

        $response = Response::json([
            'ok'        => true,
            'csrf'      => Csrf::issue($result->token, $secret),
            'user'      => self::userBody($user),
            'self_test' => $selfTest->toArray(),
        ]);
        $response->headers['Set-Cookie'] = $this->sessions->cookieHeader(
            $result->token,
            $request->isHttps(),
            $request->basePath(),
            $now
        );

        return $response;
    }

    public function me(Request $request): Response
    {
        $secret = $this->csrfSecret();
        $token = $request->cookie(AuthSessions::COOKIE);

        if ($secret === null) {
            return $this->corrupt();
        }

        $user = $request->principal === null ? null : $request->principal->user;

        if ($user === null || $token === null || $token === '') {
            return Response::json(['error' => 'unauthenticated'], 401);
        }

        return Response::json(['user' => self::userBody($user), 'csrf' => Csrf::issue($token, $secret)]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->cookie(AuthSessions::COOKIE);

        if ($token !== null && $token !== '') {
            $this->sessions->logout($token);
        }

        if ($request->principal !== null) {
            $this->storage->auditLog()->record(AuditEvent::by(
                $request->principal,
                AuditEvent::LOGOUT,
                $request->clientIp()
            ));
        }

        $response = Response::json(['ok' => true]);
        $response->headers['Set-Cookie'] = $this->sessions->clearCookieHeader($request->isHttps(), $request->basePath());

        return $response;
    }

    private function csrfSecret(): ?string
    {
        $secret = $this->storage->config()->get('install_id');

        return $secret === null || $secret === '' ? null : $secret;
    }

    private function corrupt(): Response
    {
        return Response::json(['error' => 'corrupt_install'], 500);
    }

    /** @return array{id: int, login: string, role: string} */
    private static function userBody(User $user): array
    {
        return ['id' => $user->id, 'login' => $user->login, 'role' => $user->role];
    }
}
