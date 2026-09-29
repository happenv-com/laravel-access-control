<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * Spec §3.3, the worked examples with `Requires` and `ImpliedBy`.
 */
#[PermissionGroup(ProductGroup::class)]
enum BasicRulePermission: string implements PermissionDefinition
{
    #[ImpliedBy(self::Update)]
    case View = 'basic-rule.view';

    #[Requires(self::View)]
    case Create = 'basic-rule.create';

    case Update = 'basic-rule.update';

    #[ImpliedBy(self::Update)]
    case Manage = 'basic-rule.manage';

    case Plain = 'basic-rule.plain';
}
