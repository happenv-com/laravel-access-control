<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use InvalidArgumentException;

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
    ) {
        $this->assertDrawable($clusters, $nodes, $edges);
    }

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

    /**
     * A diagram an application builds by hand is held to what every renderer relies on: each id
     * used once, every cluster, parent and edge end known, and no cluster inside itself. Anything
     * else would be drawn wrong, quietly — a node without its cluster, an edge to nowhere.
     *
     * @param  list<DiagramCluster>  $clusters
     * @param  list<DiagramNode>  $nodes
     * @param  list<DiagramEdge>  $edges
     *
     * @throws InvalidArgumentException
     */
    private function assertDrawable(array $clusters, array $nodes, array $edges): void
    {
        $parents = [];

        foreach ($clusters as $cluster) {
            if (array_key_exists($cluster->id, $parents)) {
                throw new InvalidArgumentException(sprintf('Two clusters share the id [%s].', $cluster->id));
            }

            $parents[$cluster->id] = $cluster->parent;
        }

        foreach ($parents as $id => $parent) {
            $seen = [$id => true];

            while ($parent !== null) {
                if (! array_key_exists($parent, $parents)) {
                    throw new InvalidArgumentException(sprintf('Cluster [%s] sits in a cluster the diagram does not have: [%s].', $id, $parent));
                }

                if (isset($seen[$parent])) {
                    throw new InvalidArgumentException(sprintf('Cluster [%s] sits inside itself.', $id));
                }

                $seen[$parent] = true;
                $parent = $parents[$parent];
            }
        }

        $ids = [];

        foreach ($nodes as $node) {
            if (isset($ids[$node->id])) {
                throw new InvalidArgumentException(sprintf('Two nodes share the id [%s].', $node->id));
            }

            if ($node->cluster !== null && ! array_key_exists($node->cluster, $parents)) {
                throw new InvalidArgumentException(sprintf('Node [%s] sits in a cluster the diagram does not have: [%s].', $node->id, $node->cluster));
            }

            $ids[$node->id] = true;
        }

        foreach ($edges as $edge) {
            foreach ([$edge->from, $edge->to] as $end) {
                if (! isset($ids[$end])) {
                    throw new InvalidArgumentException(sprintf('Edge points at a node the diagram does not have: [%s].', $end));
                }
            }
        }
    }
}
