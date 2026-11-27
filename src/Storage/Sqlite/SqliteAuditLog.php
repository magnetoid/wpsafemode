<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use PDO;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\AuditEventRow;
use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Redact;

final class SqliteAuditLog implements AuditLog
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function record(AuditEvent $event): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO audit_log (at, actor, event, subject, meta_json, ip)
             VALUES (:at, :actor, :event, :subject, :meta, :ip)'
        );

        $stmt->execute([
            ':at'      => $event->at,
            ':actor'   => $event->actor,
            ':event'   => $event->event,
            ':subject' => $event->subject,
            ':meta'    => json_encode(Redact::meta($event->meta), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ':ip'      => $event->ip,
        ]);
    }

    public function recent(int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }

        $stmt = $this->connection->pdo()->prepare(
            'SELECT at, actor, event, subject, meta_json, ip FROM audit_log ORDER BY at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $events = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $meta = is_string($row['meta_json']) ? json_decode($row['meta_json'], true) : null;
            $events[] = AuditEventRow::hydrate($row, $meta);
        }

        return $events;
    }
}
