<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;

/**
 * A reason given as a closure — PHP 8.5 syntax. Loaded only by a test that is skipped on older
 * versions, where this file does not parse.
 */
#[PermissionGroup(ProductGroup::class)]
enum ClosureReasonPermission: string implements PermissionDefinition
{
    #[Requires(self::View, reason: static function (string $permission, string $other): string {
        return $permission . ' needs ' . $other;
    })]
    case Edit = 'closure-reason.edit';

    case View = 'closure-reason.view';
}
