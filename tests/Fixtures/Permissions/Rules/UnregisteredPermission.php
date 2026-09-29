<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Never registered: the enum of a module that is switched off.
 */
#[PermissionGroup(ProductGroup::class)]
enum UnregisteredPermission: string implements PermissionDefinition
{
    case Orphan = 'unregistered.orphan';
}
