<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;

/** Owner-only actions on the installed tool itself. */
final class AdminController
{
    public function __construct(private Storage $storage, private string $appDir)
    {
    }

    public function rename(Request $request): Response
    {
        if (!$request->bodyHasTypes(['name' => 'string'])) {
            return Response::badRequest();
        }

        $name = $request->json()['name'] ?? SelfRename::suggest();

        if (!is_string($name) || !SelfRename::isSafeName($name)) {
            return Response::json(['error' => 'rename_failed', 'message' => SelfRename::UNSAFE_NAME_MESSAGE], 422);
        }

        $principal = $request->principal;

        if ($principal === null) {
            return Response::json(['error' => 'unauthenticated'], 401);
        }

        // Every storage write happens before the move: the data directory
        // lives inside the app directory, and a write after it would
        // recreate the old, guessable path.
        $this->storage->auditLog()->record(
            AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name])
        );

        // Build the answer before the move. The autoloader reads from this
        // directory, so after a successful rename no class can be loaded:
        // nothing below the move may touch a class that is not loaded yet.
        // The session cookie is scoped to the old path, so the owner signs in again.
        $answer = Response::json(['ok' => true, 'redirect' => '../' . $name . '/', 'sign_in_again' => true]);

        try {
            SelfRename::rename($this->appDir, $name);
        } catch (StorageException $e) {
            $this->storage->auditLog()->record(
                AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name, 'outcome' => 'failed'])
            );

            return Response::json(['error' => 'rename_failed', 'message' => $e->getMessage()], 422);
        }

        return $answer;
    }
}
