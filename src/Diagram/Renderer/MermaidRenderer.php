<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\PermissionState;
use Happenv\LaravelAccessControl\Diagram\Renderer\Support\DiagramIdentifiers;
use Happenv\LaravelAccessControl\Diagram\Renderer\Support\EdgeText;
use Happenv\LaravelAccessControl\Diagram\Renderer\Support\StatePalette;

/**
 * A Mermaid `flowchart` — for a Markdown document, or a page that loads mermaid.js.
 */
final class MermaidRenderer implements DiagramRenderer
{
    public function format(): string
    {
        return 'mermaid';
    }

    public function render(PermissionDiagram $diagram): string
    {
        $ids = new DiagramIdentifiers($diagram);
        $lines = ['flowchart LR', ...$this->body($diagram, $ids, null, 1)];

        foreach ($diagram->edges as $edge) {
            $lines[] = '    ' . $this->edge($edge, $ids);
        }

        return implode("\n", [...$lines, ...$this->styles($diagram, $ids)]);
    }

    /**
     * @return list<string> the nodes of a cluster (outside every cluster, for null), then its child
     *                      clusters, nested
     */
    private function body(PermissionDiagram $diagram, DiagramIdentifiers $ids, ?string $cluster, int $depth): array
    {
        $indent = str_repeat('    ', $depth);
        $lines = [];

        foreach ($diagram->nodesIn($cluster) as $node) {
            $lines[] = $indent . $this->node($node, $ids);
        }

        foreach ($diagram->clustersIn($cluster) as $child) {
            $lines[] = sprintf('%ssubgraph %s["%s"]', $indent, $ids->cluster($child->id), $this->escape($child->label));
            array_push($lines, ...$this->body($diagram, $ids, $child->id, $depth + 1));
            $lines[] = $indent . 'end';
        }

        return $lines;
    }

    private function node(DiagramNode $node, DiagramIdentifiers $ids): string
    {
        $id = $ids->node($node->id);

        return match ($node->kind) {
            NodeKind::Principal => sprintf('%s(["%s"])', $id, $this->escape($node->label)),
            NodeKind::Role, NodeKind::Direct => sprintf('%s["%s"]', $id, $this->escape($node->label)),
            NodeKind::Permission => sprintf('%s("%s")', $id, $this->permissionLabel($node)),
        };
    }

    private function permissionLabel(DiagramNode $node): string
    {
        $value = $node->permission instanceof PermissionDefinition ? (string) $node->permission->value : null;

        return $value === null || $value === $node->label
            ? $this->escape($node->label)
            : $this->escape($node->label) . '<br/>' . $this->escape($value);
    }

    private function edge(DiagramEdge $edge, DiagramIdentifiers $ids): string
    {
        $arrow = match ($edge->kind) {
            EdgeKind::Grants => '-.->',
            EdgeKind::Implies => '==>',
            EdgeKind::ConflictsWith => '--x',
            default => '-->',
        };

        $text = EdgeText::of($edge);

        return $text === null
            ? sprintf('%s %s %s', $ids->node($edge->from), $arrow, $ids->node($edge->to))
            : sprintf('%s %s|"%s"| %s', $ids->node($edge->from), $arrow, $this->escape($text), $ids->node($edge->to));
    }

    /**
     * A class per state that a node has, in the order the states are declared.
     *
     * @return list<string>
     */
    private function styles(PermissionDiagram $diagram, DiagramIdentifiers $ids): array
    {
        $byState = [];

        foreach ($diagram->nodes as $node) {
            if ($node->state instanceof PermissionState) {
                $byState[$node->state->value][] = $ids->node($node->id);
            }
        }

        $lines = [];

        foreach (PermissionState::cases() as $state) {
            if (! isset($byState[$state->value])) {
                continue;
            }

            $class = 'state_' . str_replace('-', '_', $state->value);

            $lines[] = sprintf(
                '    classDef %s fill:%s,stroke:%s%s',
                $class,
                StatePalette::fill($state),
                StatePalette::stroke($state),
                StatePalette::dashed($state) ? ',stroke-dasharray:4 2' : '',
            );
            $lines[] = sprintf('    class %s %s', implode(',', $byState[$state->value]), $class);
        }

        return $lines;
    }

    /**
     * Mermaid's entity codes for what would end a quoted label or read as markup.
     */
    private function escape(string $text): string
    {
        return str_replace(['"', '<', '>', "\r\n", "\n", "\r"], ['#quot;', '#lt;', '#gt;', ' ', ' ', ' '], $text);
    }
}
