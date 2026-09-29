<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    resolve(PermissionRegistry::class)->register(BasicRulePermission::class);
});

describe('getEffectivePermissions', function (): void {
    it('lists the registered permissions the principal may act on, rules applied', function (): void {
        $user = new User;
        $user->permissions = ['basic-rule.update', 'basic-rule.create'];

        // Update brings View and Manage along; Create requires View, which Update implies.
        expect($user->getEffectivePermissions())->toBeInstanceOf(Collection::class)
            ->and($user->getEffectivePermissions()->all())->toBe([
                BasicRulePermission::View,
                BasicRulePermission::Create,
                BasicRulePermission::Update,
                BasicRulePermission::Manage,
            ]);
    });

    it('leaves out what a restriction withholds, but not what it implies', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === BasicRulePermission::Update);

        $user = new User;
        $user->permissions = ['basic-rule.update'];

        expect($user->getEffectivePermissions()->all())->toBe([
            BasicRulePermission::View,
            BasicRulePermission::Manage,
        ]);
    });

    it('lists only registered permissions, since only those are known', function (): void {
        $user = new User;
        $user->permissions = ['category.delete', 'basic-rule.plain'];

        expect($user->getEffectivePermissions()->all())->toBe([BasicRulePermission::Plain]);
    });

    it('resolves a principal holding roles over the union of their grants', function (): void {
        $holder = new RoleHolder([
            new InMemoryGrantRole(['basic-rule.create']),
            new InMemoryGrantRole(['basic-rule.update']),
        ]);

        expect($holder->getEffectivePermissions()->all())->toBe([
            BasicRulePermission::View,
            BasicRulePermission::Create,
            BasicRulePermission::Update,
            BasicRulePermission::Manage,
        ]);
    });

    it('works on a grant container using HasPermissions alone', function (): void {
        expect((new InMemoryRole(['basic-rule.plain']))->getEffectivePermissions()->all())
            ->toBe([BasicRulePermission::Plain]);
    });
});

describe('AccessControl::effectivePermissions', function (): void {
    it('asks any principal, including one answering hasPermissionTo() itself', function (): void {
        // An administrator short-circuit, say: the list follows whatever the principal answers.
        $principal = new class implements AuthControllable
        {
            public function hasPermissionTo(PermissionDefinition $permission): bool
            {
                return $permission !== BasicRulePermission::Plain;
            }
        };

        expect(AccessControl::effectivePermissions($principal)->all())->toBe([
            BasicRulePermission::View,
            BasicRulePermission::Create,
            BasicRulePermission::Update,
            BasicRulePermission::Manage,
        ]);
    });
});
