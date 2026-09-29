<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\GateConfigurator;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\CrossRolePermission;
use Happenv\LaravelAccessControl\VoterRegistry;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    resolve(PermissionRegistry::class)->register([
        BasicRulePermission::class,
        CrossRolePermission::class,
    ]);
});

describe('HasPermissions with rules', function (): void {
    it('applies the rules over the stored grants', function (): void {
        $role = new InMemoryRole(['basic-rule.create', 'basic-rule.update']);

        expect($role->hasPermissionTo(BasicRulePermission::Manage))->toBeTrue()
            ->and($role->hasPermissionTo(BasicRulePermission::Create))->toBeTrue()
            ->and($role->hasPermissionTo(BasicRulePermission::Plain))->toBeFalse();
    });

    it('denies a permission whose requirement is missing', function (): void {
        expect((new InMemoryRole(['basic-rule.create']))->hasPermissionTo(BasicRulePermission::Create))->toBeFalse();
    });

    it('withholds a restricted permission, not what it implies', function (): void {
        // A read-only mode withholding Update must not take away what Update implies: a restriction
        // withholds rather than revokes, and the rules read the grants.
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === BasicRulePermission::Update);

        $role = new InMemoryRole(['basic-rule.update']);

        expect($role->hasPermissionTo(BasicRulePermission::Update))->toBeFalse()
            ->and($role->hasPermissionTo(BasicRulePermission::Manage))->toBeTrue();
    });

    describe('writing grants', function (): void {
        beforeEach(function (): void {
            $this->user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'permissions' => [],
            ]);
        });

        it('stores exactly the permission it is given, never what it implies', function (): void {
            $this->user->givePermissionTo(BasicRulePermission::Update);

            expect($this->user->fresh()->permissions)->toBe(['basic-rule.update'])
                ->and($this->user->hasPermissionTo(BasicRulePermission::Manage))->toBeTrue();
        });

        it('keeps a revoked permission effective while something still implies it', function (): void {
            $this->user->update(['permissions' => ['basic-rule.manage', 'basic-rule.update']]);

            $this->user->revokePermissionTo(BasicRulePermission::Manage);

            expect($this->user->fresh()->permissions)->toBe(['basic-rule.update'])
                ->and($this->user->hasPermissionTo(BasicRulePermission::Manage))->toBeTrue();
        });
    });
});

describe('HasRoles with rules', function (): void {
    it('resolves over the union of the roles', function (array $first, array $second, PermissionDefinition $permission, bool $withGrants, bool $fallback): void {
        $holding = new RoleHolder([new InMemoryGrantRole($first), new InMemoryGrantRole($second)]);
        $legacy = new RoleHolder([new InMemoryRole($first), new InMemoryRole($second)]);

        expect($holding->hasPermissionTo($permission))->toBe($withGrants)
            ->and($legacy->hasPermissionTo($permission))->toBe($fallback);
    })->with([
        'implied and required across roles' => [['cross-role.update'], ['cross-role.view'], CrossRolePermission::Create, true, true],
        // The one difference: a role that cannot hand over its grants answers with its own rules
        // applied, and on its own it does not store the requirement.
        'a requirement split across roles' => [['cross-role.create'], ['cross-role.view'], CrossRolePermission::Create, true, false],
        'a conflict split across roles' => [['cross-role.view-own'], ['cross-role.view-any'], CrossRolePermission::ViewOwn, false, false],
    ]);

    it('asks both kinds of role when a principal holds both', function (): void {
        $holder = new RoleHolder([
            new InMemoryGrantRole(['cross-role.update']),
            new InMemoryRole(['cross-role.view']),
        ]);

        expect($holder->hasPermissionTo(CrossRolePermission::Create))->toBeTrue();
    });

    it('reads the grants of its roles once per instance', function (): void {
        $role = new class(['cross-role.view']) extends InMemoryGrantRole
        {
            public int $reads = 0;

            public function getGrants(): iterable
            {
                $this->reads++;

                return parent::getGrants();
            }
        };

        $holder = new RoleHolder([$role]);

        $holder->hasPermissionTo(CrossRolePermission::View);
        $holder->hasPermissionTo(CrossRolePermission::Create);
        $holder->hasPermissionTo(CrossRolePermission::ViewOwn);

        expect($role->reads)->toBe(1);
    });

    it('reads the grants again after forgetResolvedPermissions()', function (): void {
        $role = new InMemoryGrantRole(['cross-role.view']);
        $holder = new RoleHolder([$role]);

        expect($holder->hasPermissionTo(CrossRolePermission::View))->toBeTrue();

        $role->givePermissionTo(CrossRolePermission::Update);

        // A permission not asked before still reads the grants loaded at the first check.
        expect($holder->hasPermissionTo(CrossRolePermission::Create))->toBeFalse();

        $holder->forgetResolvedPermissions();

        expect($holder->hasPermissionTo(CrossRolePermission::Create))->toBeTrue();
    });

    it('answers an ability string by the roles alone, without rules', function (): void {
        $holder = new RoleHolder([
            new class implements AuthControllable
            {
                public function hasPermissionTo($permission): bool
                {
                    return ($permission instanceof BackedEnum ? $permission->value : $permission) === 'basic-rule.create';
                }
            },
        ]);

        // As an enum the permission requires View, which no role grants; as a string it has no rules.
        expect($holder->hasPermissionTo('basic-rule.create'))->toBeTrue()
            ->and($holder->hasPermissionTo(BasicRulePermission::Create))->toBeFalse();
    });
});

describe('HasRolesAndPermissions with rules', function (): void {
    beforeEach(function (): void {
        $this->user = new class extends User
        {
            protected $table = 'users';

            public function getRoles(): iterable
            {
                return [new InMemoryGrantRole(['cross-role.create'])];
            }
        };
    });

    it('resolves once over the union of direct and role grants', function (): void {
        // The dependent comes from a role, its requirement is stored directly.
        $this->user->permissions = ['cross-role.view'];

        expect($this->user->hasPermissionTo(CrossRolePermission::Create))->toBeTrue();
    });

    it('answers an ability string by the direct grants and the roles', function (): void {
        // This used to throw a TypeError: the direct check only takes a permission enum. The fixture
        // User holds no roles — a role using HasPermissions cannot answer a string either.
        $user = new User;
        $user->permissions = ['category.delete'];

        expect($user->hasPermissionTo('category.delete'))->toBeTrue()
            ->and($user->hasPermissionTo('category.create'))->toBeFalse();
    });
});

describe('the Gate with rules', function (): void {
    beforeEach(function (): void {
        resolve(GateConfigurator::class)->configure();

        $this->actingAs(User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'permissions' => ['basic-rule.update'],
        ]));
    });

    it('allows a permission implied by a stored one', function (): void {
        expect(Gate::allows(BasicRulePermission::View))->toBeTrue();
    });

    it('runs the voters of the implied permission', function (): void {
        // Allowed first, so the refusal below can only come from the voter.
        expect(Gate::allows(BasicRulePermission::View))->toBeTrue();

        resolve(VoterRegistry::class)->register(
            BasicRulePermission::View,
            fn (Authenticatable $user): Response => Response::deny('View is vetoed.'),
        );

        expect(Gate::allows(BasicRulePermission::View))->toBeFalse();
    });

    it('does not run the voters of the permission that implies it', function (): void {
        resolve(VoterRegistry::class)->register(
            BasicRulePermission::Update,
            fn (Authenticatable $user): Response => Response::deny('Update is vetoed.'),
        );

        expect(Gate::allows(BasicRulePermission::View))->toBeTrue()
            ->and(Gate::allows(BasicRulePermission::Update))->toBeFalse();
    });
});
