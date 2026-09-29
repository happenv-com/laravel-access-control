<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

/**
 * A diagram under construction: nodes, edges and clusters in the order they were first added, each
 * once. The builders fill it; `toDiagram()` freezes it.
 *
 * @internal
 */
final class DiagramDraft
{
    /**
     * @var array<string, DiagramCluster>
     */
    private array $clusters = [];

    /**
     * @var array<string, DiagramNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, DiagramEdge>
     */
    private array $edges = [];

    public function __construct(
        private readonly DiagramKind $kind,
    ) {}

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function addCluster(DiagramCluster $cluster): void
    {
        $this->clusters[$cluster->id] ??= $cluster;
    }

    public function addNode(DiagramNode $node): void
    {
        $this->nodes[$node->id] ??= $node;
    }

    public function addEdge(DiagramEdge $edge): void
    {
        $this->edges[$edge->from . '|' . $edge->to . '|' . $edge->kind->value] ??= $edge;
    }

    public function toDiagram(): PermissionDiagram
    {
        return new PermissionDiagram(
            $this->kind,
            array_values($this->clusters),
            array_values($this->nodes),
            array_values($this->edges),
        );
    }
}
