<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.4, M3: a cycle of implications — legal, and the three come together.
 */
#[PermissionGroup(ProductGroup::class)]
enum ImplicationCyclePermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::B)]
    case A = 'm3.a';

    #[ImpliedBy(self::C)]
    case B = 'm3.b';

    #[ImpliedBy(self::A)]
    case C = 'm3.c';
}
