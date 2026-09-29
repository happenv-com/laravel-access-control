<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
enum RequirementCyclePermission: string implements PermissionDefinition
{
    #[Requires(self::B)]
    case A = 'requirement-cycle.a';

    #[Requires(self::C)]
    case B = 'requirement-cycle.b';

    #[Requires(self::A)]
    case C = 'requirement-cycle.c';
}
