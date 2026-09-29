<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.2, the layer invariant for conflicts: C is active, yet not allowed — it loses its own
 * conflict with D. P's conflict with C must read the first, never the second.
 */
#[PermissionGroup(ProductGroup::class)]
enum ChainedConflictPermission: string implements PermissionDefinition
{
    #[ConflictsWith(self::C)]
    case P = 'chained-conflict.p';

    #[ConflictsWith(self::D)]
    case C = 'chained-conflict.c';

    case D = 'chained-conflict.d';
}
