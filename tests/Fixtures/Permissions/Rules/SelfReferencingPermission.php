<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * A rule about the permission itself — a `ConflictsWith`, which no cycle check would catch.
 */
#[PermissionGroup(ProductGroup::class)]
enum SelfReferencingPermission: string implements PermissionDefinition
{
    #[ConflictsWith(self::Loop)]
    case Loop = 'self-referencing.loop';
}
