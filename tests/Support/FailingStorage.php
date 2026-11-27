<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Support;

use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Contracts\AuthSessionStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Contracts\UserStore;

/**
 * Wraps a real Storage and makes chosen config writes fail, the way a full
 * disk, a quota or a request killed mid-write would. The failure is an
 * uncoded StorageException whose message carries an absolute path, exactly
 * what a real backend throws.
 */
final class FailingStorage implements Storage
{
    public const MESSAGE = 'Cannot write /var/www/site/wpsafemode/data/config.php';

    /** Config writes so far, failed ones included. */
    public int $writes = 0;

    /** @var list<string> keys whose write went through, in order */
    public array $written = [];

    /** @param \Closure(string, int): bool $fails given the key and the write's 1-based number */
    public function __construct(private Storage $inner, private \Closure $fails)
    {
    }

    public static function onKey(Storage $inner, string $key): self
    {
        return new self($inner, static fn (string $k): bool => $k === $key);
    }

    public function beforeWrite(string $key): void
    {
        $this->writes++;

        if (($this->fails)($key, $this->writes)) {
            throw new StorageException(self::MESSAGE);
        }

        $this->written[] = $key;
    }

    public function config(): ConfigStore
    {
        $inner = $this->inner->config();
        $owner = $this;

        return new class ($inner, $owner) implements ConfigStore {
            public function __construct(private ConfigStore $inner, private FailingStorage $owner)
            {
            }

            public function get(string $key, ?string $default = null): ?string
            {
                return $this->inner->get($key, $default);
            }

            public function set(string $key, string $value): void
            {
                $this->owner->beforeWrite($key);
                $this->inner->set($key, $value);
            }

            public function has(string $key): bool
            {
                return $this->inner->has($key);
            }

            public function delete(string $key): void
            {
                $this->inner->delete($key);
            }

            public function all(): array
            {
                return $this->inner->all();
            }
        };
    }

    public function sessions(): SessionRepo
    {
        return $this->inner->sessions();
    }

    public function messages(): MessageLog
    {
        return $this->inner->messages();
    }

    public function users(): UserStore
    {
        return $this->inner->users();
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->inner->rateLimiter();
    }

    public function authSessions(): AuthSessionStore
    {
        return $this->inner->authSessions();
    }

    public function auditLog(): AuditLog
    {
        return $this->inner->auditLog();
    }

    public function backend(): string
    {
        return $this->inner->backend();
    }
}
