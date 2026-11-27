<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/** What a route demands of its caller. A closed set, mapped onto roles. */
final class Permission
{
    public const OPEN       = 'open';        // anyone, including anonymous
    public const READ       = 'read';        // viewer and above
    public const OPERATE    = 'operate';     // operator and above
    public const ADMINISTER = 'administer';  // admin and above
    public const OWN        = 'own';         // owner only

    public const ALL = [self::OPEN, self::READ, self::OPERATE, self::ADMINISTER, self::OWN];

    /** $role is null for an anonymous caller. An unknown permission is never allowed. */
    public static function allows(string $permission, ?string $role): bool
    {
        if ($permission === self::OPEN) {
            return true;
        }

        if ($role === null) {
            return false;
        }

        switch ($permission) {
            case self::READ:
                return Role::atLeast($role, Role::VIEWER);
            case self::OPERATE:
                return Role::atLeast($role, Role::OPERATOR);
            case self::ADMINISTER:
                return Role::atLeast($role, Role::ADMIN);
            case self::OWN:
                return $role === Role::OWNER;
            default:
                return false;
        }
    }
}
