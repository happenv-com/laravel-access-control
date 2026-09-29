<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionGraph;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\Diagram\DiagramCluster;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\PermissionState;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;
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

/**
 * One small principal diagram, built by hand, that every renderer is checked against: a principal
 * holding a role that stores one permission, whose requirement is not granted.
 */
function sampleDiagram(): PermissionDiagram
{
    return new PermissionDiagram(
        DiagramKind::Principal,
        [
            new DiagramCluster('group:products', 'Products'),
            new DiagramCluster('subject:gallery', 'Gallery', 'group:products'),
        ],
        [
            new DiagramNode('principal', NodeKind::Principal, 'Jan'),
            new DiagramNode('role:0', NodeKind::Role, 'Editor'),
            new DiagramNode('permission:gallery.view', NodeKind::Permission, 'View Gallery', GalleryPermission::View, PermissionState::Allowed, 'subject:gallery'),
            new DiagramNode('permission:product.view', NodeKind::Permission, 'product.view', ProductPermission::View, PermissionState::NotGranted),
        ],
        [
            new DiagramEdge('principal', 'role:0', EdgeKind::Holds),
            new DiagramEdge('role:0', 'permission:gallery.view', EdgeKind::Stores),
            new DiagramEdge('permission:gallery.view', 'permission:product.view', EdgeKind::Requires, 'needs the product'),
        ],
    );
}
