<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
enum DuplicateRulePermission: string implements PermissionDefinition
{
    #[Requires(self::View)]
    #[Requires(self::View)]
    case Edit = 'duplicate-rule.edit';

    case View = 'duplicate-rule.view';
}
