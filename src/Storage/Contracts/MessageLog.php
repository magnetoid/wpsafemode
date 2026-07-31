<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\Message;

/**
 * Append-only. There is no update and no delete by design: the message
 * history is the record replayed to the API, and rewriting it retroactively
 * would corrupt an in-flight conversation.
 */
interface MessageLog
{
    /**
     * @param array<int, mixed> $content
     * @return int the sequence number assigned to this message
     */
    public function append(string $sessionId, string $role, array $content, ?string $apiMessageId = null): int;

    /** @return iterable<Message> in ascending seq order */
    public function all(string $sessionId): iterable;

    public function count(string $sessionId): int;
}
