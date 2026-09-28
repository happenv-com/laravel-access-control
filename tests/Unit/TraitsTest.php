<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\CategoryPermission;
use Happenv\LaravelAccessControl\Traits\HasRoles;

beforeEach(function (): void {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
});

describe('HasRoles trait', function (): void {
    it('returns true when a role has the permission', function (): void {
        // Create a class that uses HasRoles directly
        $userWithRoles = new class
        {
            use HasRoles;

            private array $rolesCollection = [];

            public function getRoles(): iterable
            {
                return $this->rolesCollection;
            }

            public function setRoles(array $roles): void
            {
                $this->rolesCollection = $roles;
            }
        };

        // Create a mock role that HAS the permission
        $role = new class
        {
            public function hasPermissionTo($permission): bool
            {
                return true; // This role has the permission
            }
        };

        $userWithRoles->setRoles([$role]);

        // This will iterate through roles and find the permission
        $result = $userWithRoles->hasPermissionTo(CategoryPermission::Create);
        expect($result)->toBeTrue();
    });

    it('iterates through all roles to check permissions', function (): void {
        $userWithRoles = new class
        {
            use HasRoles;

            private array $rolesCollection = [];

            public function getRoles(): iterable
            {
                return $this->rolesCollection;
            }

            public function setRoles(array $roles): void
            {
                $this->rolesCollection = $roles;
            }
        };

        // Create a role that doesn't have the permission
        $role1 = new class
        {
            public function hasPermissionTo($permission): bool
            {
                return false;
            }
        };

        $userWithRoles->setRoles([$role1]);

        // This will iterate through roles and check permissions
        $result = $userWithRoles->hasPermissionTo(CategoryPermission::Create);
        expect($result)->toBeFalse();
    });

    it('returns false when no roles are set', function (): void {
        $userWithRoles = new class
        {
            use HasRoles;

            public function getRoles(): iterable
            {
                return [];
            }
        };

        $result = $userWithRoles->hasPermissionTo(CategoryPermission::Create);
        expect($result)->toBeFalse();
    });

    it('memoises the resolution and does not re-iterate roles', function (): void {
        $userWithRoles = new class
        {
            use HasRoles;

            private array $rolesCollection = [];

            public function getRoles(): iterable
            {
                return $this->rolesCollection;
            }

            public function setRoles(array $roles): void
            {
                $this->rolesCollection = $roles;
            }
        };

        $role = new class
        {
            public int $calls = 0;

            public function hasPermissionTo($permission): bool
            {
                $this->calls++;

                return false;
            }
        };

        $userWithRoles->setRoles([$role]);

        $userWithRoles->hasPermissionTo(CategoryPermission::Create);
        $userWithRoles->hasPermissionTo(CategoryPermission::Create);
        $userWithRoles->hasPermissionTo(CategoryPermission::Create);

        // The role is consulted once; subsequent checks hit the per-instance memo.
        expect($role->calls)->toBe(1);

        // Clearing the memo forces re-evaluation.
        $userWithRoles->forgetResolvedPermissions();
        $userWithRoles->hasPermissionTo(CategoryPermission::Create);

        expect($role->calls)->toBe(2);
    });
});

describe('HasRoles trait under a restriction', function (): void {
    beforeEach(function (): void {
        $this->userWithRoles = new class
        {
            use HasRoles;

            public function getRoles(): iterable
            {
                return [
                    new class
                    {
                        public function hasPermissionTo($permission): bool
                        {
                            return true;
                        }
                    },
                ];
            }
        };
    });

    it('withholds a restricted permission even after remembering it was granted', function (): void {
        // The memo lives as long as the model instance, and a restriction can begin or end within
        // that time. It records what the ROLES grant; the restriction is asked afresh every time.
        $readOnly = false;

        AccessControl::restrictUsing(function (PermissionDefinition $permission) use (&$readOnly): bool {
            return $readOnly;
        });

        expect($this->userWithRoles->hasPermissionTo(CategoryPermission::Create))->toBeTrue();

        $readOnly = true;

        expect($this->userWithRoles->hasPermissionTo(CategoryPermission::Create))->toBeFalse();

        $readOnly = false;

        expect($this->userWithRoles->hasPermissionTo(CategoryPermission::Create))->toBeTrue();
    });

    it('checks an ability string by the roles alone', function (): void {
        // Restrictions are keyed by permission enum; the trait also accepts plain ability strings
        // and must not hand one to a closure typed for an enum.
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => true);

        expect($this->userWithRoles->hasPermissionTo('category.create'))->toBeTrue()
            ->and($this->userWithRoles->hasPermissionTo(CategoryPermission::Create))->toBeFalse();
    });
});

describe('HasRolesAndPermissions trait', function (): void {
    it('checks direct permissions first', function (): void {
        $this->user->givePermissionTo(CategoryPermission::Delete);

        expect($this->user->hasPermissionTo(CategoryPermission::Delete))->toBeTrue();
    });

    it('returns false when user has no permissions or roles with permission', function (): void {
        expect($this->user->hasPermissionTo(CategoryPermission::Create))->toBeFalse();
    });

    it('stores a direct grant even when a role already grants it', function (): void {
        // A direct grant is its own fact. Skipping it because a role happens to grant the same
        // permission today would take it away the day the role is withdrawn.
        $userWithRole = new class extends User
        {
            protected $table = 'users';

            public function getRoles(): iterable
            {
                return [
                    new class
                    {
                        public function hasPermissionTo($permission): bool
                        {
                            return true;
                        }
                    },
                ];
            }
        };

        $userWithRole->fill(['name' => 'Role User', 'email' => 'role@example.com', 'password' => 'password'])->save();

        $userWithRole->givePermissionTo(CategoryPermission::Delete);

        expect($userWithRole->fresh()->permissions)->toBe(['category.delete']);
    });

    it('it combines both traits correctly', function (): void {
        // This test ensures the trait properly calls both hasDirectPermissionTo and hasRolePermissionTo
        expect($this->user->hasPermissionTo(CategoryPermission::Create))->toBeFalse();
    });
});
