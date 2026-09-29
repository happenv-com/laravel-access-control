<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\PermissionConditions;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\Flags;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\RequiresFlag;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionedPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;

beforeEach(function (): void {
    Flags::reset();
});

describe('PermissionConditions', function (): void {
    it('reads a condition declared on the enum for every case', function (): void {
        expect(resolve(PermissionConditions::class)->for(ConditionedPermission::Plain))
            ->toEqual([new RequiresFlag('verified')]);
    });

    it('reads a condition declared on one case', function (): void {
        expect(resolve(PermissionConditions::class)->for(ConditionRulePermission::Alone))
            ->toEqual([new RequiresFlag('mfa')]);
    });

    it('sums the enum\'s conditions and the case\'s, the enum\'s first', function (): void {
        expect(resolve(PermissionConditions::class)->for(ConditionedPermission::Guarded))
            ->toEqual([new RequiresFlag('verified'), new RequiresFlag('mfa')]);
    });

    it('finds nothing on a permission without conditions', function (): void {
        expect(resolve(PermissionConditions::class)->for(ProductPermission::View))->toBe([]);
    });

    it('keeps the attribute instances for the life of the process', function (): void {
        $conditions = resolve(PermissionConditions::class);

        expect($conditions->for(ConditionedPermission::Guarded)[0])->toBe($conditions->for(ConditionedPermission::Guarded)[0])
            ->and(resolve(PermissionConditions::class))->toBe($conditions);
    });

    it('names the conditions an account fails, and none it meets', function (): void {
        $conditions = resolve(PermissionConditions::class);
        $account = new InMemoryAccount;

        Flags::raise($account, 'verified');

        expect($conditions->unmet(ConditionedPermission::Guarded, $account))->toEqual([new RequiresFlag('mfa')])
            ->and($conditions->metBy(ConditionedPermission::Guarded, $account))->toBeFalse();

        Flags::raise($account, 'mfa');

        expect($conditions->unmet(ConditionedPermission::Guarded, $account))->toBe([])
            ->and($conditions->metBy(ConditionedPermission::Guarded, $account))->toBeTrue();
    });

    it('never evaluates a principal that is not Authenticatable', function (): void {
        $conditions = resolve(PermissionConditions::class);

        expect($conditions->unmet(ConditionedPermission::Guarded, new InMemoryRole))->toBe([])
            ->and($conditions->metBy(ConditionedPermission::Guarded, new InMemoryRole))->toBeTrue()
            ->and(Flags::$checks)->toBe(0);
    });

    it('asks again on every check — an answer is never remembered', function (): void {
        $conditions = resolve(PermissionConditions::class);
        $account = new InMemoryAccount;

        expect($conditions->metBy(ConditionRulePermission::Alone, $account))->toBeFalse();

        Flags::raise($account);

        expect($conditions->metBy(ConditionRulePermission::Alone, $account))->toBeTrue()
            ->and(Flags::$checks)->toBe(2);
    });

    it('stops at the first condition the account fails', function (): void {
        resolve(PermissionConditions::class)->metBy(ConditionedPermission::Guarded, new InMemoryAccount);

        expect(Flags::$checks)->toBe(1);
    });

    it('hands each condition the permission it guards', function (): void {
        resolve(PermissionConditions::class)->metBy(ConditionedPermission::Plain, new InMemoryAccount);

        expect(Flags::$checked)->toBe(ConditionedPermission::Plain);
    });
});
