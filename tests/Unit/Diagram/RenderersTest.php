<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\DiagramCluster;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\Renderer\DiagramRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\DiagramRendererRegistry;
use Happenv\LaravelAccessControl\Diagram\Renderer\DotRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\JsonRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\MermaidRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\TreeRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\UnsupportedDiagramFormatException;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;

/**
 * The edges sampleDiagram() does not have, and labels that need escaping.
 */
function edgeKindsDiagram(): PermissionDiagram
{
    return new PermissionDiagram(
        DiagramKind::Catalogue,
        [],
        [
            new DiagramNode('role:0', NodeKind::Role, 'Legacy "role"'),
            new DiagramNode('permission:a', NodeKind::Permission, 'A <b>'),
            new DiagramNode('permission:b', NodeKind::Permission, 'B'),
            new DiagramNode('permission:c', NodeKind::Permission, 'C'),
        ],
        [
            new DiagramEdge('role:0', 'permission:a', EdgeKind::Grants),
            new DiagramEdge('permission:a', 'permission:b', EdgeKind::Implies),
            new DiagramEdge('permission:b', 'permission:c', EdgeKind::ConflictsWith, 'four eyes'),
        ],
    );
}

describe('TreeRenderer', function (): void {
    it('walks what a principal holds, then lists what it does not store', function (): void {
        expect((new TreeRenderer)->render(sampleDiagram()))->toBe(<<<'TREE'
            Jan
            └── Editor
                └── View Gallery (gallery.view) [allowed]
                    └── requires product.view: needs the product
            not stored
            └── product.view [not granted]
            TREE);
    });

    it('draws a catalogue as its clusters, each permission with its rules', function (): void {
        $diagram = new PermissionDiagram(
            DiagramKind::Catalogue,
            [
                new DiagramCluster('group:products', 'Products'),
                new DiagramCluster('subject:gallery', 'Gallery', 'group:products'),
            ],
            [
                new DiagramNode('permission:gallery.view', NodeKind::Permission, 'View Gallery', GalleryPermission::View, null, 'subject:gallery'),
                new DiagramNode('permission:gallery.manage', NodeKind::Permission, 'Manage Gallery', GalleryPermission::Manage, null, 'subject:gallery'),
                new DiagramNode('permission:product.update', NodeKind::Permission, 'product.update', ProductPermission::Update),
            ],
            [
                new DiagramEdge('permission:product.update', 'permission:gallery.manage', EdgeKind::Implies),
                new DiagramEdge('permission:gallery.manage', 'permission:gallery.view', EdgeKind::ConflictsWith, 'four eyes'),
            ],
        );

        expect((new TreeRenderer)->render($diagram))->toBe(<<<'TREE'
            product.update
            └── implies Manage Gallery
            Products
            └── Gallery
                ├── View Gallery (gallery.view)
                └── Manage Gallery (gallery.manage)
                    ├── implied by product.update
                    └── conflicts with View Gallery: four eyes
            TREE);
    });
});

describe('MermaidRenderer', function (): void {
    it('draws a flowchart with nested clusters and a class per state', function (): void {
        expect((new MermaidRenderer)->render(sampleDiagram()))->toBe(<<<'MERMAID'
            flowchart LR
                n0(["Jan"])
                n1["Editor"]
                n3("product.view")
                subgraph c0["Products"]
                    subgraph c1["Gallery"]
                        n2("View Gallery<br/>gallery.view")
                    end
                end
                n0 --> n1
                n1 --> n2
                n2 -->|"requires: needs the product"| n3
                classDef state_allowed fill:#dcfce7,stroke:#16a34a
                class n2 state_allowed
                classDef state_not_granted fill:#ffffff,stroke:#9ca3af,stroke-dasharray:4 2
                class n3 state_not_granted
            MERMAID);
    });

    it('draws each kind of edge its own way, and escapes labels', function (): void {
        expect((new MermaidRenderer)->render(edgeKindsDiagram()))->toBe(<<<'MERMAID'
            flowchart LR
                n0["Legacy #quot;role#quot;"]
                n1("A #lt;b#gt;")
                n2("B")
                n3("C")
                n0 -.->|"may act"| n1
                n1 ==>|"implies"| n2
                n2 --x|"conflicts with: four eyes"| n3
            MERMAID);
    });
});

describe('DotRenderer', function (): void {
    it('draws a digraph with nested clusters and coloured states', function (): void {
        expect((new DotRenderer)->render(sampleDiagram()))->toBe(<<<'DOT'
            digraph permissions {
                rankdir=LR;
                node [shape=box, style="rounded,filled", fillcolor="#ffffff", fontname="Helvetica"];
                edge [fontname="Helvetica"];
                n0 [label="Jan", shape=ellipse];
                n1 [label="Editor"];
                n3 [label="product.view", fillcolor="#ffffff", color="#9ca3af", style="rounded,filled,dashed"];
                subgraph cluster_c0 {
                    label="Products";
                    subgraph cluster_c1 {
                        label="Gallery";
                        n2 [label="View Gallery\ngallery.view", fillcolor="#dcfce7", color="#16a34a"];
                    }
                }
                n0 -> n1;
                n1 -> n2;
                n2 -> n3 [label="requires: needs the product"];
            }
            DOT);
    });

    it('draws each kind of edge its own way, and escapes labels', function (): void {
        expect((new DotRenderer)->render(edgeKindsDiagram()))->toBe(<<<'DOT'
            digraph permissions {
                rankdir=LR;
                node [shape=box, style="rounded,filled", fillcolor="#ffffff", fontname="Helvetica"];
                edge [fontname="Helvetica"];
                n0 [label="Legacy \"role\""];
                n1 [label="A <b>"];
                n2 [label="B"];
                n3 [label="C"];
                n0 -> n1 [label="may act", style=dashed];
                n1 -> n2 [label="implies", penwidth=2];
                n2 -> n3 [label="conflicts with: four eyes", color="#dc2626", arrowhead=tee];
            }
            DOT);
    });

    it('refuses an edge to a node the diagram does not have', function (): void {
        $diagram = new PermissionDiagram(DiagramKind::Catalogue, [], [], [new DiagramEdge('a', 'b', EdgeKind::Requires)]);

        expect(fn (): string => (new DotRenderer)->render($diagram))
            ->toThrow(InvalidArgumentException::class, 'Edge points at a node the diagram does not have: [a].');
    });
});

describe('JsonRenderer', function (): void {
    it('hands the diagram over as its array', function (): void {
        expect(json_decode((new JsonRenderer)->render(sampleDiagram()), true))->toBe(sampleDiagram()->toArray());
    });
});

describe('DiagramRendererRegistry', function (): void {
    it('offers the built-in formats', function (): void {
        expect(resolve(DiagramRendererRegistry::class)->formats())->toBe(['tree', 'mermaid', 'dot', 'json']);
    });

    it('names the formats it has when asked for one it has not', function (): void {
        expect(fn (): DiagramRenderer => resolve(DiagramRendererRegistry::class)->get('svg'))
            ->toThrow(UnsupportedDiagramFormatException::class, 'No diagram renderer for format [svg]. Available: tree, mermaid, dot, json.');
    });

    it('takes a renderer an application tags, and lets a later one replace a format', function (): void {
        app()->bind('app.renderer.csv', fn (): DiagramRenderer => new class implements DiagramRenderer
        {
            public function format(): string
            {
                return 'csv';
            }

            public function render(PermissionDiagram $diagram): string
            {
                return 'csv';
            }
        });
        app()->bind('app.renderer.mermaid', fn (): DiagramRenderer => new class implements DiagramRenderer
        {
            public function format(): string
            {
                return 'mermaid';
            }

            public function render(PermissionDiagram $diagram): string
            {
                return 'own mermaid';
            }
        });
        app()->tag(['app.renderer.csv', 'app.renderer.mermaid'], DiagramRendererRegistry::TAG);

        $registry = resolve(DiagramRendererRegistry::class);

        expect($registry->formats())->toBe(['tree', 'mermaid', 'dot', 'json', 'csv'])
            ->and($registry->get('mermaid')->render(sampleDiagram()))->toBe('own mermaid');
    });
});
