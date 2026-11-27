<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * The closed set of roles, lowest to highest. A closed set of constants
 * rather than an enum: PHP 8.0 has none.
 */
final class Role
{
    public const VIEWER   = 'viewer';
    public const OPERATOR = 'operator';
    public const ADMIN    = 'admin';
    public const OWNER    = 'owner';

    /** Ascending: each role includes everything below it. */
    public const ALL = [self::VIEWER, self::OPERATOR, self::ADMIN, self::OWNER];

    public static function isValid(string $role): bool
    {
        return in_array($role, self::ALL, true);
    }

    /** True when $role is $required or higher in ALL. Unknown roles are never enough. */
    public static function atLeast(string $role, string $required): bool
    {
        $have = array_search($role, self::ALL, true);
        $need = array_search($required, self::ALL, true);

        return $have !== false && $need !== false && $have >= $need;
    }
}
