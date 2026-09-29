<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\RequiresFlag;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * A condition on the enum (every case) and one more on a case — summed.
 */
#[PermissionGroup(ProductGroup::class)]
#[RequiresFlag('verified')]
enum ConditionedPermission: string implements PermissionDefinition
{
    case Plain = 'conditioned.plain';

    #[RequiresFlag('mfa')]
    case Guarded = 'conditioned.guarded';
}
