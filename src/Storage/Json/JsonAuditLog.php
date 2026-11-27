<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\GuardedJsonLines;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\AuditEventRow;
use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Redact;

final class JsonAuditLog implements AuditLog
{
    public function __construct(private string $path)
    {
    }

    public function record(AuditEvent $event): void
    {
        GuardedJsonLines::append($this->path, [
            'at'      => $event->at,
            'actor'   => $event->actor,
            'event'   => $event->event,
            'subject' => $event->subject,
            'meta'    => Redact::meta($event->meta),
            'ip'      => $event->ip,
        ]);
    }

    public function recent(int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }

        $events = [];

        foreach (GuardedJsonLines::stream($this->path) as $row) {
            $events[] = AuditEventRow::hydrate($row, $row['meta'] ?? null);
        }

        // Reverse first, then a stable sort: events sharing a timestamp stay
        // newest-appended first. PHP 8.0 sorts are stable.
        $events = array_reverse($events);
        usort($events, static fn (AuditEvent $a, AuditEvent $b): int => $b->at <=> $a->at);

        return array_slice($events, 0, $limit);
    }
}
