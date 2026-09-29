<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer\Support;

use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use InvalidArgumentException;

/**
 * Identifiers every graph language accepts — `n<i>` for nodes, `c<i>` for clusters — in place of
 * the diagram's own ids, which carry dots, colons and whatever a permission value holds.
 *
 * @internal
 */
final readonly class DiagramIdentifiers
{
    /**
     * @var array<string, string>
     */
    private array $nodes;

    /**
     * @var array<string, string>
     */
    private array $clusters;

    public function __construct(PermissionDiagram $diagram)
    {
        $nodes = [];
        $clusters = [];

        foreach ($diagram->nodes as $index => $node) {
            $nodes[$node->id] = 'n' . $index;
        }

        foreach ($diagram->clusters as $index => $cluster) {
            $clusters[$cluster->id] = 'c' . $index;
        }

        $this->nodes = $nodes;
        $this->clusters = $clusters;
    }

    public function node(string $id): string
    {
        return $this->nodes[$id] ?? throw new InvalidArgumentException(sprintf(
            'Edge points at a node the diagram does not have: [%s].',
            $id,
        ));
    }

    public function cluster(string $id): string
    {
        return $this->clusters[$id];
    }
}
