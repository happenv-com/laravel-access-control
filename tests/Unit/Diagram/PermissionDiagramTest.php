<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\DiagramCluster;
use Happenv\LaravelAccessControl\Diagram\DiagramDraft;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\PermissionState;

describe('PermissionDiagram', function (): void {
    it('hands itself over as data, with its schema', function (): void {
        expect(sampleDiagram()->toArray())->toBe([
            'schema' => ['name' => 'access-control.permission-diagram', 'version' => 2],
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

    it('refuses what its renderers could not draw', function (array $clusters, array $nodes, array $edges, string $message): void {
        // A diagram an application builds by hand is held to what every renderer relies on.
        expect(fn (): PermissionDiagram => new PermissionDiagram(DiagramKind::Catalogue, $clusters, $nodes, $edges))
            ->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'an edge to a node it does not have' => [[], [], [new DiagramEdge('a', 'b', EdgeKind::Requires)], 'Edge points at a node the diagram does not have: [a].'],
        'two nodes with one id' => [[], [new DiagramNode('a', NodeKind::Permission, 'A'), new DiagramNode('a', NodeKind::Permission, 'B')], [], 'Two nodes share the id [a].'],
        'a node in a cluster it does not have' => [[], [new DiagramNode('a', NodeKind::Permission, 'A', cluster: 'c')], [], 'Node [a] sits in a cluster the diagram does not have: [c].'],
        'two clusters with one id' => [[new DiagramCluster('c', 'C'), new DiagramCluster('c', 'D')], [], [], 'Two clusters share the id [c].'],
        'a cluster in a cluster it does not have' => [[new DiagramCluster('c', 'C', 'p')], [], [], 'Cluster [c] sits in a cluster the diagram does not have: [p].'],
        'a cluster inside itself' => [[new DiagramCluster('c', 'C', 'd'), new DiagramCluster('d', 'D', 'c')], [], [], 'Cluster [c] sits inside itself.'],
    ]);

    it('tells the structural edges from the rules', function (): void {
        expect(array_map(fn (EdgeKind $kind): bool => $kind->isStructural(), EdgeKind::cases()))
            ->toBe([true, true, true, false, false, false]);
    });

    it('refuses a schema version it does not support', function (): void {
        expect(fn (): PermissionDiagram => new PermissionDiagram(DiagramKind::Catalogue, [], [], [], 3))
            ->toThrow(InvalidArgumentException::class, 'Unsupported schema version [3]. Supported: 1, 2.')
            ->and(fn (): PermissionDiagram => sampleDiagram()->forSchema(3))
            ->toThrow(InvalidArgumentException::class, 'Unsupported schema version [3]. Supported: 1, 2.');
    });

    it('draws schema version 1 the way 3.0 drew it: an unmet condition as denied', function (): void {
        $diagram = new PermissionDiagram(
            DiagramKind::Principal,
            [],
            [
                new DiagramNode('principal', NodeKind::Principal, 'Jan'),
                new DiagramNode('permission:a', NodeKind::Permission, 'A', state: PermissionState::UnmetCondition),
                new DiagramNode('permission:b', NodeKind::Permission, 'B', state: PermissionState::Allowed),
            ],
            [],
        );

        $downgraded = $diagram->forSchema(1);

        expect($downgraded->schemaVersion)->toBe(1)
            ->and($downgraded->node('permission:a')?->state)->toBe(PermissionState::Denied)
            ->and($downgraded->node('permission:b')?->state)->toBe(PermissionState::Allowed)
            ->and($downgraded->toArray()['schema']['version'])->toBe(1)
            ->and($diagram->node('permission:a')?->state)->toBe(PermissionState::UnmetCondition);
    });

    it('is equal to the original for its own schema version', function (): void {
        $diagram = sampleDiagram();

        expect($diagram->forSchema(2))->toEqual($diagram);
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
