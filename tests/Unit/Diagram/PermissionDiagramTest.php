<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\DiagramCluster;
use Happenv\LaravelAccessControl\Diagram\DiagramDraft;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;

describe('PermissionDiagram', function (): void {
    it('hands itself over as data, with its schema', function (): void {
        expect(sampleDiagram()->toArray())->toBe([
            'schema' => ['name' => 'access-control.permission-diagram', 'version' => 1],
            'kind' => 'principal',
            'clusters' => [
                ['id' => 'group:products', 'label' => 'Products', 'parent' => null],
                ['id' => 'subject:gallery', 'label' => 'Gallery', 'parent' => 'group:products'],
            ],
            'nodes' => [
                ['id' => 'principal', 'kind' => 'principal', 'label' => 'Jan', 'permission' => null, 'state' => null, 'cluster' => null],
                ['id' => 'role:0', 'kind' => 'role', 'label' => 'Editor', 'permission' => null, 'state' => null, 'cluster' => null],
                ['id' => 'permission:gallery.view', 'kind' => 'permission', 'label' => 'View Gallery', 'permission' => 'gallery.view', 'state' => 'allowed', 'cluster' => 'subject:gallery'],
                ['id' => 'permission:product.view', 'kind' => 'permission', 'label' => 'product.view', 'permission' => 'product.view', 'state' => 'not-granted', 'cluster' => null],
            ],
            'edges' => [
                ['from' => 'principal', 'to' => 'role:0', 'kind' => 'holds', 'label' => null],
                ['from' => 'role:0', 'to' => 'permission:gallery.view', 'kind' => 'stores', 'label' => null],
                ['from' => 'permission:gallery.view', 'to' => 'permission:product.view', 'kind' => 'requires', 'label' => 'needs the product'],
            ],
        ]);
    });

    it('finds its nodes, clusters and edges', function (): void {
        $diagram = sampleDiagram();

        expect($diagram->node('role:0')?->label)->toBe('Editor')
            ->and($diagram->node('nope'))->toBeNull()
            ->and(array_map(fn (DiagramNode $node): string => $node->id, $diagram->nodesIn(null)))->toBe(['principal', 'role:0', 'permission:product.view'])
            ->and(array_map(fn (DiagramNode $node): string => $node->id, $diagram->nodesIn('subject:gallery')))->toBe(['permission:gallery.view'])
            ->and(array_map(fn (DiagramCluster $cluster): string => $cluster->id, $diagram->clustersIn(null)))->toBe(['group:products'])
            ->and(array_map(fn (DiagramCluster $cluster): string => $cluster->id, $diagram->clustersIn('group:products')))->toBe(['subject:gallery'])
            ->and(array_map(fn (DiagramEdge $edge): string => $edge->to, $diagram->edgesFrom('principal')))->toBe(['role:0']);
    });

    it('tells the structural edges from the rules', function (): void {
        expect(array_map(fn (EdgeKind $kind): bool => $kind->isStructural(), EdgeKind::cases()))
            ->toBe([true, true, true, false, false, false]);
    });
});

describe('DiagramDraft', function (): void {
    it('keeps each node, cluster and edge once, in the order first added', function (): void {
        $draft = new DiagramDraft(DiagramKind::Catalogue);

        $draft->addNode(new DiagramNode('a', NodeKind::Permission, 'A'));
        $draft->addNode(new DiagramNode('b', NodeKind::Permission, 'B'));
        $draft->addNode(new DiagramNode('a', NodeKind::Permission, 'A again'));
        $draft->addCluster(new DiagramCluster('c', 'C'));
        $draft->addCluster(new DiagramCluster('c', 'C again'));
        $draft->addEdge(new DiagramEdge('a', 'b', EdgeKind::Requires));
        $draft->addEdge(new DiagramEdge('a', 'b', EdgeKind::Requires, 'again'));
        $draft->addEdge(new DiagramEdge('a', 'b', EdgeKind::Implies));

        $diagram = $draft->toDiagram();

        expect($draft->hasNode('a'))->toBeTrue()
            ->and($draft->hasNode('z'))->toBeFalse()
            ->and($diagram->kind)->toBe(DiagramKind::Catalogue)
            ->and(array_map(fn (DiagramNode $node): string => $node->label, $diagram->nodes))->toBe(['A', 'B'])
            ->and(array_map(fn (DiagramCluster $cluster): string => $cluster->label, $diagram->clusters))->toBe(['C'])
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->kind, $edge->label], $diagram->edges))
            ->toBe([[EdgeKind::Requires, null], [EdgeKind::Implies, null]]);
    });
});
