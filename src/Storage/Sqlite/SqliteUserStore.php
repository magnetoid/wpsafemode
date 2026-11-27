<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use PDOException;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Contracts\UserStore;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\User;

final class SqliteUserStore implements UserStore
{
    private const INT_COLUMNS = ['id', 'created_at', 'password_changed_at', 'disabled_at'];

    public function __construct(private SqliteConnection $connection)
    {
    }

    public function isEmpty(): bool
    {
        // The first row goes through the same validating mapper as find(): a
        // bare COUNT(*) would report a store full of corrupt rows as
        // non-empty, but it would also never notice the corruption.
        $row = $this->connection->pdo()->query('SELECT * FROM users ORDER BY id LIMIT 1')->fetch();

        if ($row === false) {
            return true;
        }

        $this->hydrate($row);

        return false;
    }

    public function create(string $login, string $role, Credential $credential): User
    {
        $login = User::normalizeLogin($login);
        $this->assertLoginAndRole($login, $role);

        $pdo = $this->connection->pdo();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (login, role, password_hash, hash_algo, created_at, password_changed_at, disabled_at)
                 VALUES (:login, :role, :hash, :algo, :created, :changed, NULL)'
            );
            $stmt->execute([
                ':login'   => $login,
                ':role'    => $role,
                ':hash'    => $credential->hash,
                ':algo'    => $credential->algo,
                ':created' => $credential->createdAt,
                ':changed' => $credential->changedAt,
            ]);
        } catch (PDOException $e) {
            throw $this->translate($e);
        }

        return new User((int) $pdo->lastInsertId(), $login, $role, $credential);
    }

    public function createIfEmpty(string $login, string $role, Credential $credential): ?User
    {
        $login = User::normalizeLogin($login);
        $this->assertLoginAndRole($login, $role);

        $pdo = $this->connection->pdo();

        // BEGIN IMMEDIATE takes the write lock before the existence check, so a
        // concurrent first-owner request waits and then sees the row. Ended
        // with exec('COMMIT'/'ROLLBACK'), as in SqliteRateLimiter.
        $pdo->exec('BEGIN IMMEDIATE');

        try {
            if (!$this->isEmpty()) {
                $pdo->exec('ROLLBACK');

                return null;
            }

            $user = $this->create($login, $role, $credential);
            $pdo->exec('COMMIT');

            return $user;
        } catch (\Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable $ignored) {
                // The original failure is the one worth reporting, as in
                // SqliteMigrator::runStep().
            }

            throw $e;
        }
    }

    public function find(int $id): ?User
    {
        $stmt = $this->connection->pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByLogin(string $login): ?User
    {
        $login = User::normalizeLogin($login);

        if (!User::isValidLogin($login)) {
            return null;
        }

        $stmt = $this->connection->pdo()->prepare('SELECT * FROM users WHERE login = :login');
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function all(): array
    {
        $users = [];

        foreach ($this->connection->pdo()->query('SELECT * FROM users ORDER BY id')->fetchAll() as $row) {
            $users[] = $this->hydrate($row);
        }

        return $users;
    }

    public function update(User $user): void
    {
        $login = User::normalizeLogin($user->login);
        $this->assertLoginAndRole($login, $user->role);

        try {
            $stmt = $this->connection->pdo()->prepare(
                'UPDATE users SET login = :login, role = :role, password_hash = :hash,
                    hash_algo = :algo, password_changed_at = :changed, disabled_at = :disabled
                 WHERE id = :id'
            );
            $stmt->execute([
                ':login'    => $login,
                ':role'     => $user->role,
                ':hash'     => $user->credential->hash,
                ':algo'     => $user->credential->algo,
                ':changed'  => $user->credential->changedAt,
                ':disabled' => $user->disabledAt,
                ':id'       => $user->id,
            ]);
        } catch (PDOException $e) {
            throw $this->translate($e);
        }

        if ($stmt->rowCount() === 0) {
            throw new StorageException('No such user: ' . $user->id);
        }
    }

    private function assertLoginAndRole(string $normalizedLogin, string $role): void
    {
        if (!User::isValidLogin($normalizedLogin)) {
            throw new StorageException('Invalid login.');
        }

        if (!Role::isValid($role)) {
            throw new StorageException('Unknown role.');
        }
    }

    private function translate(PDOException $e): StorageException
    {
        // 23000 is the integrity-constraint class: here, the UNIQUE login.
        if ((string) $e->getCode() === '23000') {
            return new StorageException('That login is already taken.', 0, $e);
        }

        return new StorageException('User store failure: ' . $e->getMessage(), 0, $e);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        // pdo_sqlite hands INTEGER columns back as strings before PHP 8.1.
        // Digit-only strings are accepted as the integers they are; anything
        // else (text written into an INTEGER column) stays a string and is
        // refused by User::fromRow().
        foreach (self::INT_COLUMNS as $column) {
            if (isset($row[$column]) && is_string($row[$column]) && preg_match('/^-?\d+\z/', $row[$column]) === 1) {
                $row[$column] = (int) $row[$column];
            }
        }

        return User::fromRow($row);
    }
}
