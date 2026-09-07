<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\AvailableFor;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
#[AvailableFor(TestSurface::Machine)]
enum ClassSurfacedPermission: string implements PermissionDefinition
{
    case View = 'class-surfaced.view';

    #[AvailableFor(TestSurface::Panel)]
    case Update = 'class-surfaced.update';
}
