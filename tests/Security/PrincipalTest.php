<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Permission;
use WPSafeMode\Security\Principal;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\User;

final class PrincipalTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: list<string>}> */
    public static function permissionMatrix(): array
    {
        return [
            'anonymous' => [null, [Permission::OPEN]],
            'viewer'    => [Role::VIEWER, [Permission::OPEN, Permission::READ]],
            'operator'  => [Role::OPERATOR, [Permission::OPEN, Permission::READ, Permission::OPERATE]],
            'admin'     => [Role::ADMIN, [Permission::OPEN, Permission::READ, Permission::OPERATE, Permission::ADMINISTER]],
            'owner'     => [Role::OWNER, Permission::ALL],
        ];
    }

    /**
     * @dataProvider permissionMatrix
     * @param list<string> $allowed
     */
    public function testEachRoleHoldsExactlyItsPermissions(?string $role, array $allowed): void
    {
        $principal = $role === null
            ? Principal::anonymous(Channel::BROWSER)
            : Principal::forUser($this->user(1, $role), Channel::BROWSER);

        foreach (Permission::ALL as $permission) {
            self::assertSame(in_array($permission, $allowed, true), $principal->can($permission), (string) $role . ' / ' . $permission);
            self::assertSame(in_array($permission, $allowed, true), Permission::allows($permission, $role));
        }
    }

    public function testAnUnknownPermissionIsRefusedForEveryone(): void
    {
        self::assertFalse(Permission::allows('nonsense', Role::OWNER));
        self::assertFalse(Permission::allows('nonsense', null));
    }

    public function testLabelsIdentifyTheActor(): void
    {
        self::assertSame('anonymous', Principal::anonymous(Channel::BROWSER)->label());

        $principal = Principal::forUser($this->user(7, Role::ADMIN), Channel::REMOTE);

        self::assertSame('user:7', $principal->label());
        self::assertSame(7, $principal->userId());
        self::assertSame(Role::ADMIN, $principal->role());
        self::assertSame(Channel::REMOTE, $principal->channel);
        self::assertFalse($principal->isAnonymous());
        self::assertTrue(Principal::anonymous(Channel::BROWSER)->isAnonymous());
    }

    public function testAnUnknownChannelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Principal::anonymous('carrier-pigeon');
    }

    public function testAnUnknownChannelIsRefusedForUsersToo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Principal::forUser($this->user(1, Role::VIEWER), 'carrier-pigeon');
    }

    private function user(int $id, string $role): User
    {
        return new User($id, 'user' . $id, $role, new Credential('h', 'bcrypt', 1, 1));
    }
}
