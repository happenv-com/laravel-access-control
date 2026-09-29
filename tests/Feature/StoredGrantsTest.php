<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHoldingUser;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\SelfAnsweringAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;

beforeEach(function (): void {
    resolve(PermissionRegistry::class)->register(BasicRulePermission::class);
});

describe('storedGrantsOf()', function (): void {
    it('reads the direct grants and a HoldsGrants role\'s, raw', function (): void {
        $user = new RoleHoldingUser;
        $user->permissions = ['basic-rule.plain'];
        $user->heldRoles = [new InMemoryGrantRole(['basic-rule.create'])];

        $stored = AccessControl::storedGrantsOf($user);

        // Create is read as stored although its requirement (View) is missing: raw, no rules.
        expect($stored(BasicRulePermission::Plain))->toBeTrue()
            ->and($stored(BasicRulePermission::Create))->toBeTrue()
            ->and($stored(BasicRulePermission::View))->toBeFalse()
            ->and($stored(BasicRulePermission::Update))->toBeFalse();
    });

    it('asks a role that is not HoldsGrants, which answers with its own rules applied', function (): void {
        $user = new RoleHoldingUser;
        $user->heldRoles = [new InMemoryRole(['basic-rule.update'])];

        $stored = AccessControl::storedGrantsOf($user);

        expect($stored(BasicRulePermission::Update))->toBeTrue()
            ->and($stored(BasicRulePermission::Manage))->toBeTrue()
            ->and($stored(BasicRulePermission::Plain))->toBeFalse();
    });

    it('reads a restricted permission of an asked role as absent, as HasRoles does', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === BasicRulePermission::Update);

        $user = new RoleHoldingUser;
        $user->heldRoles = [new InMemoryRole(['basic-rule.update'])];

        expect(AccessControl::storedGrantsOf($user)(BasicRulePermission::Update))->toBeFalse();
    });

    it('falls back to what a principal answering hasPermissionTo() itself says', function (): void {
        expect(AccessControl::storedGrantsOf(new SelfAnsweringAccount)(BasicRulePermission::Plain))->toBeTrue();
    });

    it('resolves, through explainer(), to what hasPermissionTo() answers', function (): void {
        $user = new RoleHoldingUser;
        $user->permissions = ['basic-rule.create'];
        $user->heldRoles = [new InMemoryGrantRole(['basic-rule.update'])];

        $explain = resolve(PermissionResolver::class)->explainer(AccessControl::storedGrantsOf($user), $user);

        foreach (BasicRulePermission::cases() as $permission) {
            expect($explain($permission)->effective)->toBe($user->hasPermissionTo($permission), $permission->name);
        }
    });
});

describe('roleGrantsOf()', function (): void {
    it('reads the roles only, never the direct grants', function (): void {
        $user = new RoleHoldingUser;
        $user->permissions = ['basic-rule.plain'];
        $user->heldRoles = [new InMemoryGrantRole(['basic-rule.create'])];

        $roles = AccessControl::roleGrantsOf($user);

        expect($roles(BasicRulePermission::Plain))->toBeFalse()
            ->and($roles(BasicRulePermission::Create))->toBeTrue();
    });

    it('reads nothing for a principal without roles', function (): void {
        expect(AccessControl::roleGrantsOf(new InMemoryAccount(['basic-rule.plain']))(BasicRulePermission::Plain))->toBeFalse();
    });
});
