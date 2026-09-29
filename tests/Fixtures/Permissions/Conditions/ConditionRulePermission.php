<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\RequiresFlag;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * The spec's interaction examples: rules, a restriction and a condition (`RequiresFlag`, "mfa").
 */
#[PermissionGroup(ProductGroup::class)]
enum ConditionRulePermission: string implements PermissionDefinition
{
    // A requires B; B carries the condition.
    #[Requires(self::GuardedRequirement)]
    case RequiresGuarded = 'condition-rule.requires-guarded';

    #[RequiresFlag]
    case GuardedRequirement = 'condition-rule.guarded-requirement';

    // A carries the condition and requires B.
    #[RequiresFlag]
    #[Requires(self::PlainRequirement)]
    case GuardedRequirer = 'condition-rule.guarded-requirer';

    case PlainRequirement = 'condition-rule.plain-requirement';

    // B is implied by A; A carries the condition.
    #[RequiresFlag]
    case GuardedImplier = 'condition-rule.guarded-implier';

    #[ImpliedBy(self::GuardedImplier)]
    case ImpliedByGuarded = 'condition-rule.implied-by-guarded';

    // A conflicts with B; B carries the condition.
    #[ConflictsWith(self::GuardedConflict)]
    case ConflictsWithGuarded = 'condition-rule.conflicts-with-guarded';

    #[RequiresFlag]
    case GuardedConflict = 'condition-rule.guarded-conflict';

    // A requires B; a test restricts B.
    #[Requires(self::Restricted)]
    case RequiresRestricted = 'condition-rule.requires-restricted';

    case Restricted = 'condition-rule.restricted';

    // A condition and nothing else.
    #[RequiresFlag]
    case Alone = 'condition-rule.alone';
}
