<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §7: one group of cases per row of the `problems()` table. They share an enum because
 * `problems()` reports on the whole registry; no row refers to another row's cases.
 */
#[PermissionGroup(ProductGroup::class)]
enum ProblemPermission: string implements PermissionDefinition
{
    // Requires what it conflicts with.
    #[Requires(self::DirectC)]
    #[ConflictsWith(self::DirectC)]
    case DirectP = 'problem.direct-p';

    case DirectC = 'problem.direct-c';

    // Requires it through another requirement.
    #[Requires(self::TransitiveX)]
    #[ConflictsWith(self::TransitiveC)]
    case TransitiveP = 'problem.transitive-p';

    #[Requires(self::TransitiveC)]
    case TransitiveX = 'problem.transitive-x';

    case TransitiveC = 'problem.transitive-c';

    // Implies what it conflicts with, which needs nothing else.
    #[ConflictsWith(self::ImpliesC)]
    case ImpliesP = 'problem.implies-p';

    #[ImpliedBy(self::ImpliesP)]
    case ImpliesC = 'problem.implies-c';

    // Implies it through another implication.
    #[ConflictsWith(self::ChainC)]
    case ChainP = 'problem.chain-p';

    #[ImpliedBy(self::ChainP)]
    case ChainX = 'problem.chain-x';

    #[ImpliedBy(self::ChainX)]
    case ChainC = 'problem.chain-c';

    // Implies it, but the implied one is inactive while GatedY is missing: satisfiable.
    #[ConflictsWith(self::GatedC)]
    case GatedP = 'problem.gated-p';

    #[ImpliedBy(self::GatedP)]
    #[Requires(self::GatedY)]
    case GatedC = 'problem.gated-c';

    case GatedY = 'problem.gated-y';

    // Its requirements conflict with each other: a requirement reads `active`, so satisfiable.
    #[Requires(self::SplitX)]
    #[Requires(self::SplitY)]
    case SplitP = 'problem.split-p';

    #[ConflictsWith(self::SplitY)]
    case SplitX = 'problem.split-x';

    case SplitY = 'problem.split-y';

    // Implied BY what it conflicts with: stored alone it is allowed, so satisfiable.
    #[ImpliedBy(self::ReverseC)]
    #[ConflictsWith(self::ReverseC)]
    case ReverseP = 'problem.reverse-p';

    case ReverseC = 'problem.reverse-c';

    // Points at an enum nobody registered.
    #[Requires(UnregisteredPermission::Orphan)]
    case OrphanP = 'problem.orphan-p';
}
