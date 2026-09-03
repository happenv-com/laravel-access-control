<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\PermissionDescription;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\PermissionName;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * A second subject inside ProductGroup, labelled at the class level — the shape
 * a module takes when it contributes several enums to one group.
 */
#[PermissionGroup(ProductGroup::class)]
#[PermissionName('Store Settings')]
#[PermissionDescription('Settings that apply to the whole store')]
enum StoreSettingPermission: string implements PermissionDefinition
{
    case View = 'store-setting.view';

    #[PermissionName('Change store settings')]
    case Update = 'store-setting.update';
}
