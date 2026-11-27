<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Contracts\UserStore;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\User;

/**
 * data/users.php holds {"next_id": int, "users": {"<id>": row}}. A missing
 * file is an empty store; a file that exists but does not parse into exactly
 * that shape is corruption and throws, because isEmpty() gates the installer.
 */
final class JsonUserStore implements UserStore
{
    public function __construct(private string $path)
    {
    }

    public function isEmpty(): bool
    {
        return $this->load()['users'] === [];
    }

    public function create(string $login, string $role, Credential $credential): User
    {
        $login = User::normalizeLogin($login);
        $this->assertLoginAndRole($login, $role);

        // One locked sequence: two concurrent creates must not take the same
        // id or the same login.
        return GuardedJson::withExclusiveLock($this->path, function () use ($login, $role, $credential): User {
            $state = $this->load();

            foreach ($state['users'] as $existing) {
                if ($existing->login === $login) {
                    throw new StorageException('That login is already taken.');
                }
            }

            $user = new User($state['next_id'], $login, $role, $credential);
            $state['users'][$user->id] = $user;

            $this->save($state['next_id'] + 1, $state['users']);

            return $user;
        });
    }

    public function createIfEmpty(string $login, string $role, Credential $credential): ?User
    {
        $login = User::normalizeLogin($login);
        $this->assertLoginAndRole($login, $role);

        // The emptiness check and the write share one lock, so two concurrent
        // first-owner requests cannot both see an empty store.
        return GuardedJson::withExclusiveLock($this->path, function () use ($login, $role, $credential): ?User {
            $state = $this->load();

            if ($state['users'] !== []) {
                return null;
            }

            $user = new User($state['next_id'], $login, $role, $credential);
            $this->save($state['next_id'] + 1, [$user->id => $user]);

            return $user;
        });
    }

    public function find(int $id): ?User
    {
        return $this->load()['users'][$id] ?? null;
    }

    public function findByLogin(string $login): ?User
    {
        $login = User::normalizeLogin($login);
        $users = $this->load()['users'];

        if (!User::isValidLogin($login)) {
            return null;
        }

        foreach ($users as $user) {
            if ($user->login === $login) {
                return $user;
            }
        }

        return null;
    }

    public function all(): array
    {
        $users = $this->load()['users'];
        ksort($users);

        return array_values($users);
    }

    public function update(User $user): void
    {
        $login = User::normalizeLogin($user->login);
        $this->assertLoginAndRole($login, $user->role);

        GuardedJson::withExclusiveLock($this->path, function () use ($user, $login): void {
            $state = $this->load();

            if (!isset($state['users'][$user->id])) {
                throw new StorageException('No such user: ' . $user->id);
            }

            foreach ($state['users'] as $existing) {
                if ($existing->id !== $user->id && $existing->login === $login) {
                    throw new StorageException('That login is already taken.');
                }
            }

            $stored = $state['users'][$user->id];

            // A copy, so the caller's User is untouched; created_at stays the stored one.
            $state['users'][$user->id] = new User(
                $user->id,
                $login,
                $user->role,
                new Credential(
                    $user->credential->hash,
                    $user->credential->algo,
                    $stored->credential->createdAt,
                    $user->credential->changedAt,
                ),
                $user->disabledAt,
            );

            $this->save($state['next_id'], $state['users']);
        });
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

    /**
     * Validates the whole file as it parses it.
     *
     * @return array{next_id: int, users: array<int, User>}
     * @throws StorageException
     */
    private function load(): array
    {
        if (!is_file($this->path)) {
            return ['next_id' => 1, 'users' => []];
        }

        $file = GuardedJson::read($this->path);
        $corrupt = static fn (string $why): StorageException => new StorageException(
            'Corrupt users file: ' . $why
        );

        if (!isset($file['next_id']) || !is_int($file['next_id']) || $file['next_id'] < 1) {
            throw $corrupt('next_id');
        }

        if (!isset($file['users']) || !is_array($file['users'])) {
            throw $corrupt('users');
        }

        $users = [];
        $logins = [];

        foreach ($file['users'] as $key => $row) {
            if (!is_array($row)) {
                throw $corrupt('a record is not an object');
            }

            $user = User::fromRow($row);

            if ((string) $key !== (string) $user->id) {
                throw $corrupt('a record key does not match its id');
            }

            if ($user->id >= $file['next_id']) {
                throw $corrupt('next_id would reuse an id');
            }

            if (isset($logins[$user->login])) {
                throw $corrupt('a login appears twice');
            }

            $logins[$user->login] = true;
            $users[$user->id] = $user;
        }

        return ['next_id' => $file['next_id'], 'users' => $users];
    }

    /** @param array<int, User> $users */
    private function save(int $nextId, array $users): void
    {
        ksort($users);

        $rows = [];
        foreach ($users as $user) {
            $rows[(string) $user->id] = ['id' => $user->id, 'login' => $user->login, 'role' => $user->role]
                + $user->credential->toRow()
                + ['disabled_at' => $user->disabledAt];
        }

        GuardedJson::write($this->path, ['next_id' => $nextId, 'users' => $rows]);
    }
}
