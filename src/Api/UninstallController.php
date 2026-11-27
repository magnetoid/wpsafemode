<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Uninstaller;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;

/** Owner-only: the tool deletes itself and its data. */
final class UninstallController
{
    public function __construct(private Storage $storage, private string $appDir, private string $dataDir)
    {
    }

    public function __invoke(Request $request): Response
    {
        // Typed confirmation rather than a bare POST: this is irreversible and
        // reachable from an authenticated session, so a mis-click must not do it.
        $confirm = $request->json()['confirm'] ?? null;

        if (!is_string($confirm) || $confirm !== 'DELETE') {
            return Response::json(['error' => 'confirmation_required'], 422);
        }

        $refused = Response::json([
            'error'   => 'uninstall_refused',
            'message' => 'This copy\'s folder layout is not one the tool can safely remove itself from. Delete the tool\'s folder over FTP.',
        ], 409);

        try {
            Uninstaller::preflight($this->appDir, $this->dataDir);
        } catch (StorageException $e) {
            return $refused;
        }

        $principal = $request->principal;

        if ($principal === null) {
            return Response::json(['error' => 'unauthenticated'], 401);
        }

        // The last storage write: every JSON write recreates the data
        // directory, so nothing may write after the delete below.
        $this->storage->auditLog()->record(
            AuditEvent::by($principal, AuditEvent::UNINSTALL, $request->clientIp())
        );

        // Both answers are built before anything is deleted: the autoloader
        // reads from the directory being removed, so no class that is not
        // already loaded can be used afterwards.
        $done = Response::json([
            'ok'      => true,
            'message' => 'WP Safe Mode has removed itself. This page will not load again.',
        ]);
        $incomplete = Response::json([
            'error'   => 'uninstall_incomplete',
            'message' => 'Some files could not be removed. Delete the tool\'s folder over FTP.',
        ], 500);

        try {
            Uninstaller::run($this->appDir, $this->dataDir);
        } catch (StorageException $e) {
            return $incomplete;
        }

        return $done;
    }
}
