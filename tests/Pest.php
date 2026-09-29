<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionGraph;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * A graph over a registry of its own, holding exactly the given enums.
 *
 * @param  class-string<PermissionDefinition>  ...$enums
 */
function graphOver(string ...$enums): PermissionGraph
{
    $registry = new PermissionRegistry;
    $registry->register($enums);

    return new PermissionGraph($registry);
}

/**
 * A resolver over a registry of its own, holding exactly the given enums.
 *
 * @param  class-string<PermissionDefinition>  ...$enums
 */
function resolverOver(string ...$enums): PermissionResolver
{
    return new PermissionResolver(graphOver(...$enums));
}

/**
 * A stored-state closure over the given permission values — what a role editor would pass.
 *
 * @return Closure(PermissionDefinition): bool
 */
function storing(string ...$values): Closure
{
    return fn (PermissionDefinition $permission): bool => in_array($permission->value, $values, true);
}
