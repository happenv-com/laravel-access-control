<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

/**
 * A drawing of permissions — nodes, the edges between them and the clusters they sit in — that
 * knows no output format. A renderer turns it into text; `toArray()` hands it to an application as
 * data, with the schema it follows.
 */
final readonly class PermissionDiagram
{
    public const string SCHEMA_NAME = 'access-control.permission-diagram';

    public const int SCHEMA_VERSION = 1;

    /**
     * @param  list<DiagramCluster>  $clusters
     * @param  list<DiagramNode>  $nodes
     * @param  list<DiagramEdge>  $edges
     */
    public function __construct(
        public DiagramKind $kind,
        public array $clusters,
        public array $nodes,
        public array $edges,
    ) {}

    public function node(string $id): ?DiagramNode
    {
        foreach ($this->nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<DiagramNode> the nodes directly in the cluster — or outside every cluster, for null
     */
    public function nodesIn(?string $cluster): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn (DiagramNode $node): bool => $node->cluster === $cluster,
        ));
    }

    /**
     * @return list<DiagramCluster> the clusters directly in the given one — or the outermost, for null
     */
    public function clustersIn(?string $parent): array
    {
        return array_values(array_filter(
            $this->clusters,
            fn (DiagramCluster $cluster): bool => $cluster->parent === $parent,
        ));
    }

    /**
     * @return list<DiagramEdge>
     */
    public function edgesFrom(string $id): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (DiagramEdge $edge): bool => $edge->from === $id,
        ));
    }

    /**
     * @return array{schema: array{name: string, version: int}, kind: string, clusters: list<array<string, mixed>>, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'schema' => ['name' => self::SCHEMA_NAME, 'version' => self::SCHEMA_VERSION],
            'kind' => $this->kind->value,
            'clusters' => array_map(fn (DiagramCluster $cluster): array => $cluster->toArray(), $this->clusters),
            'nodes' => array_map(fn (DiagramNode $node): array => $node->toArray(), $this->nodes),
            'edges' => array_map(fn (DiagramEdge $edge): array => $edge->toArray(), $this->edges),
        ];
    }
}
