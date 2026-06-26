<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\PermissionName;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

#[PermissionGroup('Product', 'Product Management')]
enum DuplicateProductPermission: string implements PermissionDefinition
{
    #[PermissionName('View Products')]
    case View = 'product.view'; // Same as ProductPermission::View

    #[PermissionName('Create Product')]
    case Create = 'product.create';
}
