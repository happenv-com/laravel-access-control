<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\GateConfigurator;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\Flags;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\RequiresFlag;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Concerns\AuthenticatesInMemory;
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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

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

    it('applies the conditions of the registered permission an ability string names', function (object $account): void {
        expect($account->hasPermissionTo('condition-rule.alone'))->toBeFalse();

        Flags::raise($account);

        expect($account->hasPermissionTo('condition-rule.alone'))->toBeTrue();
    })->with([
        'a user (HasRolesAndPermissions)' => fn (): User => tap(new User, fn (User $user) => $user->permissions = ['condition-rule.alone']),
        'an account holding roles (HasRoles)' => fn (): RoleHoldingAccount => new RoleHoldingAccount([new InMemoryGrantRole(['condition-rule.alone'])]),
    ]);

    it('leaves an ability string naming no registered permission to the grants alone', function (): void {
        $user = new User;
        $user->permissions = ['nobody.registered.this'];

        expect($user->hasPermissionTo('nobody.registered.this'))->toBeTrue()
            ->and(Flags::$checks)->toBe(0);
    });

    it('lets a condition that cannot answer fail loudly', function (): void {
        (new InMemoryAccount(['throwing-condition.broken']))->hasPermissionTo(ThrowingConditionPermission::Broken);
    })->throws(RuntimeException::class, 'The condition could not be checked.');
});

describe('the gate', function (): void {
    beforeEach(function (): void {
        resolve(GateConfigurator::class)->configure();
    });

    it('refuses an account failing a condition in the words of a missing permission (invariant 10)', function (bool $display, string $message): void {
        config(['access-control.display_permission_in_exception' => $display]);

        $failing = new InMemoryAccount(['condition-rule.alone']);
        $missing = new InMemoryAccount([]);

        expect(Gate::forUser($failing)->inspect(ConditionRulePermission::Alone)->message())->toBe($message)
            ->and(Gate::forUser($missing)->inspect(ConditionRulePermission::Alone)->message())->toBe($message);
    })->with([
        'the permission named' => [true, 'Unauthorized for condition-rule.alone'],
        'the permission not named' => [false, 'Unauthorized.'],
    ]);

    it('lets an account through once it meets the condition', function (): void {
        $account = new InMemoryAccount(['condition-rule.alone']);

        Flags::raise($account);

        expect(Gate::forUser($account)->allows(ConditionRulePermission::Alone))->toBeTrue();
    });

    it('refuses an account answering hasPermissionTo() itself until it meets the condition', function (): void {
        $account = new SelfAnsweringAccount;

        expect(Gate::forUser($account)->allows(ConditionRulePermission::Alone))->toBeFalse();

        Flags::raise($account);

        expect(Gate::forUser($account)->allows(ConditionRulePermission::Alone))->toBeTrue();
    });

    it('evaluates an account that is not AuthControllable too', function (): void {
        $account = new class implements Authenticatable
        {
            use AuthenticatesInMemory;
        };

        expect(Gate::forUser($account)->allows(ConditionRulePermission::Alone))->toBeFalse();

        Flags::raise($account);

        expect(Gate::forUser($account)->allows(ConditionRulePermission::Alone))->toBeTrue();
    });

    it('refuses no user at all before anything else', function (): void {
        expect(Gate::forUser(null)->inspect(ConditionRulePermission::Alone)->message())->toBe('Unauthenticated.')
            ->and(Flags::$checks)->toBe(0);
    });
});

describe('effectivePermissions()', function (): void {
    it('leaves out what an account fails a condition of', function (): void {
        $account = new InMemoryAccount(['condition-rule.alone', 'condition-rule.plain-requirement']);

        expect(AccessControl::effectivePermissions($account)->all())->toBe([ConditionRulePermission::PlainRequirement]);

        Flags::raise($account);

        expect(AccessControl::effectivePermissions($account)->all())->toBe([
            ConditionRulePermission::PlainRequirement,
            ConditionRulePermission::Alone,
        ]);
    });

    it('leaves out, for an account answering hasPermissionTo() itself, what is restricted or fails a condition', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ConditionRulePermission::Restricted);

        $effective = AccessControl::effectivePermissions(new SelfAnsweringAccount)->all();

        expect($effective)->toContain(ConditionRulePermission::PlainRequirement)
            ->not->toContain(ConditionRulePermission::Alone)
            ->not->toContain(ConditionRulePermission::Restricted);
    });
});

describe('unmetConditions()', function (): void {
    it('names the conditions an account fails, and none for a role', function (): void {
        expect(AccessControl::unmetConditions(ConditionRulePermission::Alone, new InMemoryAccount))->toEqual([new RequiresFlag])
            ->and(AccessControl::unmetConditions(ConditionRulePermission::Alone, new InMemoryRole))->toBe([]);
    });
});
