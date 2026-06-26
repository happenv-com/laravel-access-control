<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\CategoryGroup;

#[PermissionGroup(CategoryGroup::class)]
enum CategoryPermission: string implements PermissionDefinition
{
    case View = 'category.view';
    case Create = 'category.create';
    case Update = 'category.update';
    case Delete = 'category.delete';
}
