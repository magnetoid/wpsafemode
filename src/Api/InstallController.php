<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The installer's routes. Every mutating route and the self-test re-verify the
 * install token from the body; the Kernel refuses all of them once installed.
 */
final class InstallController
{
    public function __construct(private Installer $installer)
    {
    }

    public function state(Request $request): Response
    {
        $state = $this->installer->state();

        return Response::json($state->installed ? $state->toPublicArray() : $state->toArray());
    }

    public function verifyToken(Request $request): Response
    {
        return $this->tokenOk($request)
            ? Response::json(['ok' => true])
            : $this->badToken();
    }

    public function createOwner(Request $request): Response
    {
        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $body = $request->json();

        try {
            $this->installer->createOwner((string) ($body['login'] ?? ''), (string) ($body['password'] ?? ''));
        } catch (StorageException $e) {
            switch ($e->getCode()) {
                case Installer::ERR_WEAK_PASSWORD:
                    return Response::json(['error' => 'weak_password', 'message' => $e->getMessage()], 422);
                case Installer::ERR_BAD_LOGIN:
                    return Response::json(['error' => 'bad_login', 'message' => $e->getMessage()], 422);
                case Installer::ERR_OWNER_EXISTS:
                    return Response::json(['error' => 'owner_exists', 'message' => $e->getMessage()], 409);
                default:
                    throw $e;
            }
        }

        return Response::json($this->installer->state()->toArray());
    }

    public function selfTest(Request $request): Response
    {
        // The self-test writes into data/ and makes an outbound request, so
        // it is not anonymous.
        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($request->server);

        if ($baseUrl === null) {
            return Response::json(['error' => 'no_base_url'], 409);
        }

        return Response::json($this->installer->runSelfTest($baseUrl)->toArray());
    }

    public function apiKey(Request $request): Response
    {
        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $body = $request->json();
        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($request->server);

        try {
            $this->installer->storeApiKey(
                (string) ($body['api_key'] ?? ''),
                (bool) ($body['acknowledged_risk'] ?? false),
                $baseUrl ?? 'http://127.0.0.1:1'
            );
        } catch (StorageException $e) {
            return Response::json(['error' => 'refused', 'message' => $e->getMessage()], 422);
        }

        return Response::json($this->installer->state()->toArray());
    }

    public function finish(Request $request): Response
    {
        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        try {
            $this->installer->finalize($request->clientIp());
        } catch (StorageException $e) {
            return Response::json(['error' => 'incomplete', 'message' => $e->getMessage()], 422);
        }

        return Response::json($this->installer->state()->toPublicArray());
    }

    private function tokenOk(Request $request): bool
    {
        $token = $request->json()['token'] ?? '';

        return is_string($token) && $this->installer->verifyToken($token);
    }

    private function badToken(): Response
    {
        return Response::json(['error' => 'bad_token'], 403);
    }
}
