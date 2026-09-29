<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.4, M1: an implication through a permission that is granted but inactive.
 */
#[PermissionGroup(ProductGroup::class)]
enum ImpliedThroughInactivePermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::B)]
    case A = 'm1.a';

    #[Requires(self::C)]
    case B = 'm1.b';

    #[ImpliedBy(self::D)]
    case C = 'm1.c';

    case D = 'm1.d';
}
