<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Conditions;

use Attribute;
use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * A condition that cannot answer — its failure must reach the caller, not be read as a verdict.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class ThrowsOnCheck implements PermissionCondition
{
    public function check(PermissionDefinition $permission, Authenticatable $principal): bool
    {
        throw new RuntimeException('The condition could not be checked.');
    }
}
