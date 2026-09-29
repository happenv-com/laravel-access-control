<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\Flags;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHoldingAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHoldingUser;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\SelfAnsweringAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionedPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ThrowingConditionPermission;

beforeEach(function (): void {
    Flags::reset();

    // ConditionedPermission is left unregistered on purpose: a condition holds for any permission.
    resolve(PermissionRegistry::class)->register(ConditionRulePermission::class);
});

describe('hasPermissionTo()', function (): void {
    it('withholds a permission from an account that fails its condition', function (object $account): void {
        expect($account->hasPermissionTo(ConditionRulePermission::Alone))->toBeFalse();

        Flags::raise($account);

        expect($account->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue();
    })->with([
        'a user (HasRolesAndPermissions)' => fn (): User => tap(new User, fn (User $user) => $user->permissions = ['condition-rule.alone']),
        'a machine user (HasPermissions)' => fn (): InMemoryAccount => new InMemoryAccount(['condition-rule.alone']),
        'an account holding roles (HasRoles)' => fn (): RoleHoldingAccount => new RoleHoldingAccount([new InMemoryGrantRole(['condition-rule.alone'])]),
        'a user holding a role that is asked (HasRolesAndPermissions)' => fn (): RoleHoldingUser => tap(new RoleHoldingUser, fn (RoleHoldingUser $user) => $user->heldRoles = [new InMemoryRole(['condition-rule.alone'])]),
    ]);

    it('never evaluates a principal that is not an account', function (object $principal): void {
        expect($principal->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue()
            ->and(Flags::$checks)->toBe(0);
    })->with([
        'a role (HasPermissions)' => fn (): InMemoryRole => new InMemoryRole(['condition-rule.alone']),
        'a role handing over its grants (HoldsGrants)' => fn (): InMemoryGrantRole => new InMemoryGrantRole(['condition-rule.alone']),
        'a holder of roles that does not sign in (HasRoles)' => fn (): RoleHolder => new RoleHolder([new InMemoryGrantRole(['condition-rule.alone'])]),
    ]);

    it('lets an account answering hasPermissionTo() itself say what it says', function (): void {
        expect((new SelfAnsweringAccount)->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue();
    });

    it('asks the condition outside the memo of HasRoles, so a change within the instance counts at once', function (): void {
        $account = new RoleHoldingAccount([new InMemoryGrantRole(['condition-rule.alone'])]);

        expect($account->hasPermissionTo(ConditionRulePermission::Alone))->toBeFalse();

        Flags::raise($account);

        expect($account->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue();

        Flags::lower($account);

        expect($account->hasPermissionTo(ConditionRulePermission::Alone))->toBeFalse();
    });

    it('asks a condition only once the rules allow the permission', function (): void {
        $user = new User;
        $user->permissions = [];

        expect($user->hasPermissionTo(ConditionRulePermission::Alone))->toBeFalse()
            ->and(Flags::$checks)->toBe(0);
    });

    it('enforces a condition on a permission nobody registered', function (): void {
        $account = new InMemoryAccount(['conditioned.plain']);

        expect($account->hasPermissionTo(ConditionedPermission::Plain))->toBeFalse();

        Flags::raise($account, 'verified');

        expect($account->hasPermissionTo(ConditionedPermission::Plain))->toBeTrue();
    });

    it('leaves an ability string to the grants alone', function (): void {
        $user = new User;
        $user->permissions = ['condition-rule.alone'];

        expect($user->hasPermissionTo('condition-rule.alone'))->toBeTrue()
            ->and(Flags::$checks)->toBe(0);
    });

    it('lets a condition that cannot answer fail loudly', function (): void {
        (new InMemoryAccount(['throwing-condition.broken']))->hasPermissionTo(ThrowingConditionPermission::Broken);
    })->throws(RuntimeException::class, 'The condition could not be checked.');
});
