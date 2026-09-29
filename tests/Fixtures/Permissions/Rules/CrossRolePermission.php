<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §4.2, the cross-role examples; its conflict is also §3.3's.
 */
#[PermissionGroup(ProductGroup::class)]
enum CrossRolePermission: string implements PermissionDefinition
{
    case View = 'cross-role.view';

    #[Requires(self::View)]
    #[ImpliedBy(self::Update)]
    case Create = 'cross-role.create';

    case Update = 'cross-role.update';

    #[ConflictsWith(self::ViewAny)]
    case ViewOwn = 'cross-role.view-own';

    case ViewAny = 'cross-role.view-any';
}
