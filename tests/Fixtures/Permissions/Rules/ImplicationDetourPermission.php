<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * P comes with X and with B, and B comes with P. Stored X grants P, and through P it grants B — so
 * B is granted, yet B grants P only by way of P itself.
 */
#[PermissionGroup(ProductGroup::class)]
enum ImplicationDetourPermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::B)]
    #[ImpliedBy(self::X)]
    case P = 'implication-detour.p';

    #[ImpliedBy(self::P)]
    case B = 'implication-detour.b';

    case X = 'implication-detour.x';
}
