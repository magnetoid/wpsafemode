<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The installer's routes. Every mutating route and the self-test re-verify the
 * install token from the body; the Kernel refuses all of them once installed.
 */
final class InstallController
{
    public function __construct(private Installer $installer, private string $appDir)
    {
    }

    public function state(Request $request): Response
    {
        $state = $this->installer->state();

        // A copy that is not installed has no token until someone asks for
        // the installer, or after the file was lost, so the visit writes it
        // (idempotent). Re-read: the step depends on it.
        if (!$state->installed) {
            $this->installer->issueToken();
            $state = $this->installer->state();
        }

        return Response::json($state->installed ? $state->toPublicArray() : $state->toArray());
    }

    public function verifyToken(Request $request): Response
    {
        if (!$request->bodyHasTypes(['token' => 'string'])) {
            return Response::badRequest();
        }

        return $this->tokenOk($request)
            ? Response::json(['ok' => true])
            : $this->badToken();
    }

    public function createOwner(Request $request): Response
    {
        if (!$request->bodyHasTypes(['token' => 'string', 'login' => 'string', 'password' => 'string'])) {
            return Response::badRequest();
        }

        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $body = $request->json();
        $password = $body['password'] ?? '';

        try {
            $this->installer->createOwner($body['login'] ?? '', $password);
        } catch (StorageException $e) {
            switch ($e->getCode()) {
                case Installer::ERR_WEAK_PASSWORD:
                    // The list as well as the sentence, so the page never
                    // has to split prose back into items.
                    return Response::json([
                        'error'      => 'weak_password',
                        'message'    => $e->getMessage(),
                        'weaknesses' => PasswordHasher::weaknesses($password),
                    ], 422);
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
        if (!$request->bodyHasTypes(['token' => 'string'])) {
            return Response::badRequest();
        }

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
        if (!$request->bodyHasTypes(['token' => 'string', 'api_key' => 'string', 'acknowledged_risk' => 'bool'])) {
            return Response::badRequest();
        }

        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $body = $request->json();
        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($request->server);

        try {
            $this->installer->storeApiKey(
                $body['api_key'] ?? '',
                ($body['acknowledged_risk'] ?? false) === true,
                $baseUrl
            );
        } catch (StorageException $e) {
            switch ($e->getCode()) {
                case Installer::ERR_EMPTY_KEY:
                case Installer::ERR_EXPOSED:
                case Installer::ERR_NO_OWNER:
                    return Response::json(['error' => 'refused', 'message' => $e->getMessage()], 422);
                case Installer::ERR_ALREADY_INSTALLED:
                    return Response::json(['error' => 'already_installed'], 409);
                default:
                    // A fault, not a refusal: the composition root answers it.
                    throw $e;
            }
        }

        return Response::json($this->installer->state()->toArray());
    }

    public function finish(Request $request): Response
    {
        if (!$request->bodyHasTypes(['token' => 'string'])) {
            return Response::badRequest();
        }

        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        try {
            $this->installer->finalize($request->clientIp());
        } catch (StorageException $e) {
            switch ($e->getCode()) {
                case Installer::ERR_NO_OWNER:
                    return Response::json(['error' => 'incomplete', 'message' => $e->getMessage()], 422);
                case Installer::ERR_ALREADY_INSTALLED:
                    return Response::json(['error' => 'already_installed'], 409);
                default:
                    throw $e;
            }
        }

        return Response::json($this->installer->state()->toPublicArray());
    }

    public function rename(Request $request): Response
    {
        if (!$request->bodyHasTypes(['token' => 'string', 'name' => 'string'])) {
            return Response::badRequest();
        }

        if (!$this->tokenOk($request)) {
            return $this->badToken();
        }

        $name = $request->json()['name'] ?? SelfRename::suggest();

        if (!is_string($name) || !SelfRename::isSafeName($name)) {
            return Response::json(['error' => 'rename_failed', 'message' => SelfRename::UNSAFE_NAME_MESSAGE], 422);
        }

        $principal = $request->principal;

        if ($principal === null) {
            return $this->badToken();
        }

        // Every storage write happens before the move: the data directory
        // lives inside the app directory, and a write after it would
        // recreate the old, guessable path. Before install the principal is
        // anonymous; the install token is what authorises this.
        $this->installer->audit(AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name]));

        // Build the answer before the move. The autoloader reads from this
        // directory, so after a successful rename no class can be loaded:
        // nothing below the move may touch a class that is not loaded yet.
        $answer = Response::json(['ok' => true, 'redirect' => '../' . $name . '/']);

        try {
            SelfRename::rename($this->appDir, $name);
        } catch (StorageException $e) {
            $this->installer->audit(
                AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name, 'outcome' => 'failed'])
            );

            return Response::json(['error' => 'rename_failed', 'message' => $e->getMessage()], 422);
        }

        return $answer;
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
