<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Attributes\AvailableFor;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

#[PermissionGroup(ProductGroup::class)]
enum SurfacedPermission: string implements PermissionDefinition
{
    #[AvailableFor(TestSurface::Machine)]
    case View = 'surfaced.view';

    #[AvailableFor(TestSurface::Panel, TestSurface::Machine)]
    case Update = 'surfaced.update';

    case Delete = 'surfaced.delete';
}
