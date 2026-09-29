<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionGraph;
use Happenv\LaravelAccessControl\PermissionRegistry;
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
