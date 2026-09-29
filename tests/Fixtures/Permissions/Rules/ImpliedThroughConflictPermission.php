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
 * Spec §3.4, M2: an implication from a permission that loses a conflict.
 */
#[PermissionGroup(ProductGroup::class)]
enum ImpliedThroughConflictPermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::B)]
    #[Requires(self::C)]
    case A = 'm2.a';

    #[ConflictsWith(self::C)]
    case B = 'm2.b';

    case C = 'm2.c';
}
