<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

/**
 * One entry in the verbatim Anthropic messages array.
 *
 * $content holds the content blocks exactly as sent to or received from the
 * API. It is never a prettified projection: the whole history is replayed on
 * every turn, and a lossy round-trip breaks tool_use/tool_result pairing and
 * thinking-block signatures (spec 7.1).
 */
final class Message
{
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM    = 'system';

    /** @param array<int, mixed> $content */
    public function __construct(
        public string $sessionId,
        public int $seq,
        public string $role,
        public array $content,
        public ?string $apiMessageId,
        public int $createdAt,
    ) {
    }
}
