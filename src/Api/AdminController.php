<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;

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

        $principal = $request->principal;

        if ($principal === null) {
            return Response::json(['error' => 'unauthenticated'], 401);
        }

        // The session cookie is scoped to the old path, so the owner signs in again.
        return RenameAction::run($request, $principal, $this->appDir, function (AuditEvent $event): void {
            $this->storage->auditLog()->record($event);
        }, ['sign_in_again' => true]);
    }
}
