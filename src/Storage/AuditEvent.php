<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Security\Principal;

final class AuditEvent
{
    public const INSTALL       = 'install';
    public const LOGIN         = 'login';
    public const LOGIN_FAILED  = 'login_failed';
    public const LOGOUT        = 'logout';
    public const CONFIG_CHANGE = 'config_change';
    public const RENAME        = 'rename';
    public const UNINSTALL     = 'uninstall';
    public const SELF_TEST     = 'self_test';

    /**
     * Public so stores can hydrate rows; code that records an event goes
     * through by(), which is the only way to get an actor that is a Principal.
     *
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $at,
        public string $actor,
        public string $event,
        public ?string $subject,
        public array $meta,
        public ?string $ip,
    ) {
    }

    /** @param array<string, mixed> $meta */
    public static function by(
        Principal $actor,
        string $event,
        ?string $ip = null,
        ?string $subject = null,
        array $meta = [],
        ?int $at = null,
    ): self {
        return new self($at ?? time(), $actor->label(), $event, $subject, $meta, $ip);
    }
}
