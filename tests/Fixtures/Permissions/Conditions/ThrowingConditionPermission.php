<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\ThrowsOnCheck;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
enum ThrowingConditionPermission: string implements PermissionDefinition
{
    #[ThrowsOnCheck]
    case Broken = 'throwing-condition.broken';
}
