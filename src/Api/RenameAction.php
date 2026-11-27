<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Security\Principal;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The one rename sequence behind the installer's and the owner's endpoints:
 * check the name, audit, build the answer, move, and audit a failure.
 *
 * The order is the point. Every storage write happens before the move (R19):
 * the data directory lives inside the app directory, and a write after the
 * move would recreate the old, guessable path. The answer is built before the
 * move (R24): the autoloader reads from this directory, so after a successful
 * rename no class that is not loaded yet can be loaded.
 */
final class RenameAction
{
    /**
     * @param \Closure(AuditEvent): void $record writes one audit row
     * @param array<string, mixed> $extra added to the success answer
     */
    public static function run(Request $request, Principal $principal, string $appDir, \Closure $record, array $extra = []): Response
    {
        $name = $request->json()['name'] ?? SelfRename::suggest();

        if (!is_string($name) || !SelfRename::isSafeName($name)) {
            return self::failed(SelfRename::UNSAFE_NAME_MESSAGE);
        }

        $record(AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name]));

        $answer = Response::json(['ok' => true, 'redirect' => '../' . $name . '/'] + $extra);

        try {
            SelfRename::rename($appDir, $name);
        } catch (StorageException $e) {
            // Nothing moved, so the old path is still the one to write to.
            $record(AuditEvent::by($principal, AuditEvent::RENAME, $request->clientIp(), null, ['to' => $name, 'outcome' => 'failed']));

            // Every refusal SelfRename throws is a fixed sentence with no path.
            return self::failed($e->getMessage());
        }

        return $answer;
    }

    private static function failed(string $message): Response
    {
        return Response::json(['error' => 'rename_failed', 'message' => $message], 422);
    }
}
