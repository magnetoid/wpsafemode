<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * Full field set per spec 7.1, defined now even though M0 only exercises a
 * few fields — defining it up front avoids a schema migration in M5.
 *
 * Class constants rather than enums: enums are PHP 8.1 and the floor is 8.0.
 */
final class Session
{
    public const BLADE_RESCUE = 'rescue';
    public const BLADE_DOCTOR = 'doctor';

    public const STATUS_IDLE              = 'idle';
    public const STATUS_AWAITING_LLM      = 'awaiting_llm';
    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    public const STATUS_AWAITING_SNAPSHOT = 'awaiting_snapshot';
    public const STATUS_AWAITING_TOOL     = 'awaiting_tool';
    public const STATUS_VERIFYING         = 'verifying';
    public const STATUS_AWAITING_ROLLBACK = 'awaiting_rollback';
    public const STATUS_DONE              = 'done';
    public const STATUS_ERROR             = 'error';
    public const STATUS_ABORTED           = 'aborted';

    public function __construct(
        public string $id,
        public string $title,
        public string $blade,
        public string $status,
        public string $model,
        public string $effort,
        public int $createdAt,
        public int $updatedAt,
        public ?string $lockToken = null,
        public ?int $lockExpiresAt = null,
        public int $retryCount = 0,
        public ?int $retryAfterAt = null,
        public int $inTokens = 0,
        public int $outTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $costMicros = 0,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toRow(): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'blade'              => $this->blade,
            'status'             => $this->status,
            'model'              => $this->model,
            'effort'             => $this->effort,
            'created_at'         => $this->createdAt,
            'updated_at'         => $this->updatedAt,
            'lock_token'         => $this->lockToken,
            'lock_expires_at'    => $this->lockExpiresAt,
            'retry_count'        => $this->retryCount,
            'retry_after_at'     => $this->retryAfterAt,
            'in_tokens'          => $this->inTokens,
            'out_tokens'         => $this->outTokens,
            'cache_read_tokens'  => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'cost_micros'        => $this->costMicros,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:               (string) $row['id'],
            title:            (string) $row['title'],
            blade:            (string) $row['blade'],
            status:           (string) $row['status'],
            model:            (string) $row['model'],
            effort:           (string) $row['effort'],
            createdAt:        (int) $row['created_at'],
            updatedAt:        (int) $row['updated_at'],
            lockToken:        $row['lock_token'] === null ? null : (string) $row['lock_token'],
            lockExpiresAt:    $row['lock_expires_at'] === null ? null : (int) $row['lock_expires_at'],
            retryCount:       (int) $row['retry_count'],
            retryAfterAt:     $row['retry_after_at'] === null ? null : (int) $row['retry_after_at'],
            inTokens:         (int) $row['in_tokens'],
            outTokens:        (int) $row['out_tokens'],
            cacheReadTokens:  (int) $row['cache_read_tokens'],
            cacheWriteTokens: (int) $row['cache_write_tokens'],
            costMicros:       (int) $row['cost_micros'],
        );
    }
}
