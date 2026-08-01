<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Credential;

final class JsonAuthStore implements AuthStore
{
    /**
     * Every key Credential::fromRow() reads. Checked as a set, not just
     * password_hash: a row with the hash present but, say, created_at
     * missing still parses "successfully" — fromRow() reads the missing
     * key as null and casts it to 0 — and would hand back a Credential
     * with a valid hash and silently wrong metadata instead of the loud
     * failure current()'s docblock promises. Wrong metadata doesn't forge
     * a login (verify() only looks at hash, and password_verify()
     * self-identifies its own algorithm from the hash string), but it is
     * still corruption, not a value this store should ever hand a caller.
     */
    private const REQUIRED_KEYS = ['password_hash', 'hash_algo', 'created_at', 'password_changed_at'];

    public function __construct(private string $path)
    {
    }

    /**
     * is_file(), not current() !== null: GuardedJson::read() returns []
     * both for a file that was never written and for one that exists but
     * cannot be parsed into a credential (empty payload, or valid JSON that
     * decodes to something missing one of REQUIRED_KEYS). Deriving
     * isConfigured() from current() would conflate those two states, and
     * "corrupted" would read as "fresh install" — precisely the state a
     * first-run flow (Task 8) checks this method to rule out before it lets
     * anyone set an initial password with no prior credential to prove
     * against. A file that exists at all means something was stored here
     * once; that must block first-run setup even when its content no
     * longer parses.
     */
    public function isConfigured(): bool
    {
        return is_file($this->path);
    }

    public function store(Credential $credential): void
    {
        $existing = GuardedJson::read($this->path);

        $row = $credential->toRow();

        // Preserve the original created_at across a password change, matching
        // the SQLite ON CONFLICT clause, which does not update it.
        if (isset($existing['created_at'])) {
            $row['created_at'] = (int) $existing['created_at'];
        }

        GuardedJson::write($this->path, $row);
    }

    /**
     * @throws StorageException if the file exists but does not decode to a
     *     row carrying every one of REQUIRED_KEYS — see isConfigured()'s
     *     docblock. Silently returning null here would be the same
     *     conflation one level up: a caller using current() directly, rather
     *     than isConfigured(), to decide whether a credential already exists
     *     must not be able to mistake "corrupt" for "never configured"
     *     either.
     */
    public function current(): ?Credential
    {
        if (!is_file($this->path)) {
            return null;
        }

        $row = GuardedJson::read($this->path);

        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($row[$key])) {
                throw new StorageException('Corrupt credential file: ' . basename($this->path));
            }
        }

        return Credential::fromRow($row);
    }
}
