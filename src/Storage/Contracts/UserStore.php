<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\User;

/**
 * The people who can sign in. Ids start at 1 and are never reused.
 *
 * Every read throws StorageException when a stored record cannot be read
 * back intact. isEmpty() gates the installer, so a corrupt store must never
 * read as empty: that would let anyone create an owner.
 */
interface UserStore
{
    /** @throws StorageException if any stored user record is malformed */
    public function isEmpty(): bool;

    /** @throws StorageException invalid login, unknown role, or login already taken */
    public function create(string $login, string $role, Credential $credential): User;

    /**
     * Creates the user only when the store holds no user, atomically: of two
     * concurrent calls exactly one returns a User and the other returns null.
     * The installer uses this for the first owner.
     *
     * @return User|null null when a user already exists
     * @throws StorageException invalid login or unknown role, or a malformed stored record
     */
    public function createIfEmpty(string $login, string $role, Credential $credential): ?User;

    public function find(int $id): ?User;

    /** Normalises the login first; an invalid login simply finds nobody. */
    public function findByLogin(string $login): ?User;

    /** @return User[] ordered by id */
    public function all(): array;

    /**
     * Persists login (normalised), role, credential and disabledAt; never
     * created_at. The given User is not mutated.
     *
     * @throws StorageException unknown id, invalid login or role, login taken by another user
     */
    public function update(User $user): void;
}
