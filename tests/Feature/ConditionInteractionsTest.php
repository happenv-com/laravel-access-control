<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\GateConfigurator;
use Happenv\LaravelAccessControl\PermissionConditions;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\Flags;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\RequiresFlag;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHoldingAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\SelfAnsweringAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionRulePermission;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Flags::reset();

    resolve(PermissionRegistry::class)->register(ConditionRulePermission::class);
    resolve(GateConfigurator::class)->configure();
});

function restrictTheRestricted(): void
{
    AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ConditionRulePermission::Restricted);
}

/**
 * @return list<string>
 */
function everyConditionRuleValue(): array
{
    return array_column(ConditionRulePermission::cases(), 'value');
}

describe('rules, restrictions and conditions (the spec\'s interaction examples)', function (): void {
    it('does not carry a requirement\'s condition to what requires it', function (): void {
        $account = accountStoring('condition-rule.requires-guarded', 'condition-rule.guarded-requirement');

        expectInEffect($account, ConditionRulePermission::GuardedRequirement, false);
        expectInEffect($account, ConditionRulePermission::RequiresGuarded, true);
    });

    it('withholds the permission carrying the condition, not its requirement', function (): void {
        $account = accountStoring('condition-rule.guarded-requirer', 'condition-rule.plain-requirement');

        expectInEffect($account, ConditionRulePermission::GuardedRequirer, false);
        expectInEffect($account, ConditionRulePermission::PlainRequirement, true);
    });

    it('does not carry an implier\'s condition to what it implies', function (): void {
        $account = accountStoring('condition-rule.guarded-implier');

        expectInEffect($account, ConditionRulePermission::GuardedImplier, false);
        expectInEffect($account, ConditionRulePermission::ImpliedByGuarded, true);
    });

    it('lets a permission the account cannot use still win a conflict', function (): void {
        $account = accountStoring('condition-rule.conflicts-with-guarded', 'condition-rule.guarded-conflict');

        expectInEffect($account, ConditionRulePermission::ConflictsWithGuarded, false);
        expectInEffect($account, ConditionRulePermission::GuardedConflict, false);
    });

    it('reads a restricted requirement as stored where grants are read raw, and as absent where a role is asked', function (): void {
        restrictTheRestricted();

        $direct = accountStoring('condition-rule.requires-restricted', 'condition-rule.restricted');
        $throughGrants = new RoleHoldingAccount([new InMemoryGrantRole(['condition-rule.requires-restricted', 'condition-rule.restricted'])]);
        $throughAnswers = new RoleHoldingAccount([new InMemoryRole(['condition-rule.requires-restricted', 'condition-rule.restricted'])]);

        expectInEffect($direct, ConditionRulePermission::Restricted, false);
        expectInEffect($direct, ConditionRulePermission::RequiresRestricted, true);
        expectInEffect($throughGrants, ConditionRulePermission::RequiresRestricted, true);
        expectInEffect($throughAnswers, ConditionRulePermission::RequiresRestricted, false);
    });

    it('explains a permission the rules allow and a condition withholds', function (): void {
        $account = accountStoring('condition-rule.alone');

        expectInEffect($account, ConditionRulePermission::Alone, false);

        $resolution = resolve(PermissionResolver::class)->explainer(AccessControl::storedGrantsOf($account), $account)(ConditionRulePermission::Alone);

        expect($resolution->allowed)->toBeTrue()
            ->and($resolution->effective)->toBeFalse()
            ->and($resolution->unmetConditions)->toEqual([new RequiresFlag]);
    });

    it('puts the permissions back once the account meets the condition', function (): void {
        $account = accountStoring('condition-rule.alone', 'condition-rule.requires-guarded', 'condition-rule.guarded-requirement');

        Flags::raise($account);

        expectInEffect($account, ConditionRulePermission::Alone, true);
        expectInEffect($account, ConditionRulePermission::GuardedRequirement, true);
        expectInEffect($account, ConditionRulePermission::RequiresGuarded, true);
    });
});

describe('invariants', function (): void {
    it('1 — a condition never changes stored grants', function (): void {
        $account = accountStoring('condition-rule.alone');

        $account->hasPermissionTo(ConditionRulePermission::Alone);
        Gate::forUser($account)->allows(ConditionRulePermission::Alone);
        AccessControl::effectivePermissions($account);

        expect($account->permissions)->toBe(['condition-rule.alone']);
    });

    it('2 — a condition withholds its own permission only', function (): void {
        $account = accountStoring('condition-rule.guarded-implier', 'condition-rule.requires-guarded', 'condition-rule.guarded-requirement');

        expectInEffect($account, ConditionRulePermission::ImpliedByGuarded, true);
        expectInEffect($account, ConditionRulePermission::RequiresGuarded, true);
    });

    it('3 — a principal that is not Authenticatable is never evaluated', function (): void {
        $role = new InMemoryRole(['condition-rule.alone']);
        $holder = new RoleHolder([new InMemoryGrantRole(['condition-rule.alone'])]);

        expect($role->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue()
            ->and(AccessControl::effectivePermissions($role)->all())->toContain(ConditionRulePermission::Alone)
            ->and($holder->hasPermissionTo(ConditionRulePermission::Alone))->toBeTrue()
            ->and(AccessControl::effectivePermissions($holder)->all())->toContain(ConditionRulePermission::Alone)
            ->and(resolve(PermissionResolver::class)->explainer(AccessControl::storedGrantsOf($role))(ConditionRulePermission::Alone)->effective)->toBeTrue()
            ->and(Flags::$checks)->toBe(0);
    });

    it('4 — effectivePermissions() holds nothing restricted or with an unmet condition', function (AuthControllable $principal): void {
        restrictTheRestricted();

        $conditions = resolve(PermissionConditions::class);

        foreach (AccessControl::effectivePermissions($principal) as $permission) {
            expect(AccessControl::isRestricted($permission))->toBeFalse($permission->name)
                ->and($conditions->unmet($permission, $principal))->toBe([], $permission->name);
        }
    })->with([
        'a user storing everything' => fn (): AuthControllable => accountStoring(...everyConditionRuleValue()),
        'an account answering hasPermissionTo() itself' => fn (): AuthControllable => new SelfAnsweringAccount,
    ]);

    it('5 — hasPermissionTo() is true exactly for what effectivePermissions() lists', function (AuthControllable $principal): void {
        restrictTheRestricted();

        $effective = AccessControl::effectivePermissions($principal)->all();

        foreach (ConditionRulePermission::cases() as $permission) {
            expect($principal->hasPermissionTo($permission))->toBe(in_array($permission, $effective, true), $permission->name);
        }
    })->with([
        'a user (HasRolesAndPermissions)' => fn (): AuthControllable => accountStoring(...everyConditionRuleValue()),
        'a machine user (HasPermissions)' => fn (): AuthControllable => new InMemoryAccount(everyConditionRuleValue()),
        'an account holding a role (HasRoles)' => fn (): AuthControllable => new RoleHoldingAccount([new InMemoryGrantRole(everyConditionRuleValue())]),
        'a user meeting the condition' => fn (): AuthControllable => tap(accountStoring(...everyConditionRuleValue()), fn (object $account) => Flags::raise($account)),
    ]);

    it('6 — getGrants() returns what is stored, whatever withholds it', function (): void {
        restrictTheRestricted();

        $stored = ['condition-rule.alone', 'condition-rule.restricted', 'condition-rule.conflicts-with-guarded', 'condition-rule.guarded-conflict'];
        $account = new InMemoryAccount($stored);

        expect(collect($account->getGrants())->all())->toBe($stored);
    });
});
