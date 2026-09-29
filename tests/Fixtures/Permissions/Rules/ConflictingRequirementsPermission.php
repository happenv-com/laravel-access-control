<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.4, M5: requirements that conflict with each other.
 */
#[PermissionGroup(ProductGroup::class)]
enum ConflictingRequirementsPermission: string implements PermissionDefinition
{
    #[Requires(self::X)]
    #[Requires(self::Y)]
    case P = 'm5.p';

    #[ConflictsWith(self::Y)]
    case X = 'm5.x';

    case Y = 'm5.y';
}
