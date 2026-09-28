<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\MutatesData;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
#[MutatesData]
enum ClassMutatesDataPermission: string implements PermissionDefinition
{
    #[MutatesData(false)]
    case View = 'class-mutates-data.view';

    case Update = 'class-mutates-data.update';
}
