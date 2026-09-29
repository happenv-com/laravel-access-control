<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * X implies Y, and Y implies Z; Y also requires W. Stored X grants all three — but Y, missing W, is
 * not effective, and Z is effective only through it.
 */
#[PermissionGroup(ProductGroup::class)]
enum ImpliedChainPermission: string implements PermissionDefinition
{
    case X = 'implied-chain.x';

    #[ImpliedBy(self::X)]
    #[Requires(self::W)]
    case Y = 'implied-chain.y';

    #[ImpliedBy(self::Y)]
    case Z = 'implied-chain.z';

    case W = 'implied-chain.w';
}
