<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions;

use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;

enum TestSurface: string implements PermissionSurfaceDefinition
{
    case Panel = 'panel';

    case Machine = 'machine';
}
