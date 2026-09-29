<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\CatalogueDiagramBuilder;
use Happenv\LaravelAccessControl\Diagram\DiagramCluster;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;

describe('CatalogueDiagramBuilder', function (): void {
    it('draws every registered permission in its group and subject', function (): void {
        resolve(PermissionRegistry::class)->register([ProductPermission::class, GalleryPermission::class]);

        $diagram = resolve(CatalogueDiagramBuilder::class)->build();

        expect($diagram->kind)->toBe(DiagramKind::Catalogue)
            ->and(array_map(fn (DiagramCluster $cluster): array => [$cluster->id, $cluster->label, $cluster->parent], $diagram->clusters))->toBe([
                ['group:products', 'Products', null],
                ['subject:' . ProductPermission::class, 'Product', 'group:products'],
                ['subject:' . GalleryPermission::class, 'Gallery', 'group:products'],
            ])
            ->and(array_map(fn (DiagramNode $node): array => [$node->id, $node->label, $node->state, $node->cluster], $diagram->nodes))->toBe([
                ['permission:product.view', 'View Products', null, 'subject:' . ProductPermission::class],
                ['permission:product.create', 'Create Products', null, 'subject:' . ProductPermission::class],
                ['permission:product.update', 'Update Products', null, 'subject:' . ProductPermission::class],
                ['permission:product.delete', 'Delete Product', null, 'subject:' . ProductPermission::class],
                ['permission:gallery.view', 'View Gallery', null, 'subject:' . GalleryPermission::class],
                ['permission:gallery.manage', 'Manage Gallery', null, 'subject:' . GalleryPermission::class],
                ['permission:gallery.archive', 'Archive Gallery', null, 'subject:' . GalleryPermission::class],
            ]);
    });

    it('draws every rule a permission declares, with its translated reason', function (): void {
        resolve(PermissionRegistry::class)->register([ProductPermission::class, GalleryPermission::class]);

        $diagram = resolve(CatalogueDiagramBuilder::class)->build();

        expect(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to, $edge->kind, $edge->label], $diagram->edges))->toBe([
            ['permission:gallery.view', 'permission:product.view', EdgeKind::Requires, 'rules.needs'],
            ['permission:product.update', 'permission:gallery.manage', EdgeKind::Implies, 'Manage Gallery comes with Update Products'],
            ['permission:gallery.archive', 'permission:product.delete', EdgeKind::ConflictsWith, null],
        ]);
    });

    it('draws a rule target nobody registered after the catalogue, outside every cluster', function (): void {
        resolve(PermissionRegistry::class)->register(GalleryPermission::class);

        $diagram = resolve(CatalogueDiagramBuilder::class)->build();

        expect(array_map(fn (DiagramNode $node): array => [$node->id, $node->label, $node->cluster], array_slice($diagram->nodes, 3)))->toBe([
            ['permission:product.view', 'product.view', null],
            ['permission:product.update', 'product.update', null],
            ['permission:product.delete', 'product.delete', null],
        ])
            ->and($diagram->edges)->toHaveCount(3);
    });
});
