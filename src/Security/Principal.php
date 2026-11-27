<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Storage\User;

/**
 * Who is acting, and over which channel. Built only by the two factories and
 * never changed afterwards: the properties are private, read through
 * channel() and user() (PHP 8.0 has no readonly). It does not check that the
 * user is enabled, which is the session layer's job.
 */
final class Principal
{
    private string $channel;
    private ?User $user;

    private function __construct(string $channel, ?User $user)
    {
        if (!in_array($channel, Channel::ALL, true)) {
            throw new \InvalidArgumentException('Unknown channel: ' . $channel);
        }

        $this->channel = $channel;
        $this->user = $user;
    }

    public static function anonymous(string $channel): self
    {
        return new self($channel, null);
    }

    public static function forUser(User $user, string $channel): self
    {
        return new self($channel, $user);
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function isAnonymous(): bool
    {
        return $this->user === null;
    }

    public function role(): ?string
    {
        return $this->user === null ? null : $this->user->role;
    }

    public function userId(): ?int
    {
        return $this->user === null ? null : $this->user->id;
    }

    /** 'anonymous' or 'user:{id}', for audit lines. */
    public function label(): string
    {
        return $this->user === null ? 'anonymous' : 'user:' . $this->user->id;
    }

    public function can(string $permission): bool
    {
        return Permission::allows($permission, $this->role());
    }
}
