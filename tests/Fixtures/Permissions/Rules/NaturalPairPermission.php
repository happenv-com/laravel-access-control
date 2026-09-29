<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.4, M4: the natural pair — Update needs View and brings it — one level deeper.
 */
#[PermissionGroup(ProductGroup::class)]
enum NaturalPairPermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::Update)]
    case View = 'm4.view';

    #[Requires(self::View)]
    case Update = 'm4.update';

    #[Requires(self::Update)]
    case Delete = 'm4.delete';
}
