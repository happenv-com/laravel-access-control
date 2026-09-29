<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.3: a conflict with a permission that is inactive decides nothing.
 */
#[PermissionGroup(ProductGroup::class)]
enum GatedScopePermission: string implements PermissionDefinition
{
    #[ConflictsWith(self::ViewAny)]
    case ViewOwn = 'gated-scope.view-own';

    #[Requires(self::Extra)]
    case ViewAny = 'gated-scope.view-any';

    case Extra = 'gated-scope.extra';
}
