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
 * A Graphviz `digraph` — pipe it into `dot -Tsvg`.
 */
final class DotRenderer implements DiagramRenderer
{
    public function format(): string
    {
        return 'dot';
    }

    public function render(PermissionDiagram $diagram): string
    {
        $ids = new DiagramIdentifiers($diagram);

        $lines = [
            'digraph permissions {',
            '    rankdir=LR;',
            '    node [shape=box, style="rounded,filled", fillcolor="#ffffff", fontname="Helvetica"];',
            '    edge [fontname="Helvetica"];',
            ...$this->body($diagram, $ids, null, 1),
        ];

        foreach ($diagram->edges as $edge) {
            $lines[] = '    ' . $this->edge($edge, $ids);
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function body(PermissionDiagram $diagram, DiagramIdentifiers $ids, ?string $cluster, int $depth): array
    {
        $indent = str_repeat('    ', $depth);
        $lines = [];

        foreach ($diagram->nodesIn($cluster) as $node) {
            $lines[] = $indent . $this->node($node, $ids);
        }

        foreach ($diagram->clustersIn($cluster) as $child) {
            $lines[] = sprintf('%ssubgraph cluster_%s {', $indent, $ids->cluster($child->id));
            $lines[] = sprintf('%s    label="%s";', $indent, $this->escape($child->label));
            array_push($lines, ...$this->body($diagram, $ids, $child->id, $depth + 1));
            $lines[] = $indent . '}';
        }

        return $lines;
    }

    private function node(DiagramNode $node, DiagramIdentifiers $ids): string
    {
        $attributes = [sprintf('label="%s"', $this->label($node))];

        if ($node->kind === NodeKind::Principal) {
            $attributes[] = 'shape=ellipse';
        }

        if ($node->state instanceof PermissionState) {
            $attributes[] = sprintf('fillcolor="%s"', StatePalette::fill($node->state));
            $attributes[] = sprintf('color="%s"', StatePalette::stroke($node->state));

            if (StatePalette::dashed($node->state)) {
                $attributes[] = 'style="rounded,filled,dashed"';
            }
        }

        return sprintf('%s [%s];', $ids->node($node->id), implode(', ', $attributes));
    }

    private function label(DiagramNode $node): string
    {
        $value = $node->permission instanceof PermissionDefinition ? (string) $node->permission->value : null;

        return $value === null || $value === $node->label
            ? $this->escape($node->label)
            : $this->escape($node->label) . '\n' . $this->escape($value);
    }

    private function edge(DiagramEdge $edge, DiagramIdentifiers $ids): string
    {
        $attributes = [];
        $text = EdgeText::of($edge);

        if ($text !== null) {
            $attributes[] = sprintf('label="%s"', $this->escape($text));
        }

        array_push($attributes, ...match ($edge->kind) {
            EdgeKind::Grants => ['style=dashed'],
            EdgeKind::Implies => ['penwidth=2'],
            EdgeKind::ConflictsWith => ['color="#dc2626"', 'arrowhead=tee'],
            default => [],
        });

        $line = sprintf('%s -> %s', $ids->node($edge->from), $ids->node($edge->to));

        return $attributes === [] ? $line . ';' : sprintf('%s [%s];', $line, implode(', ', $attributes));
    }

    /**
     * A DOT quoted string: backslashes and quotes escaped, line breaks as `\n`.
     */
    private function escape(string $text): string
    {
        return str_replace(['\\', '"', "\r\n", "\n", "\r"], ['\\\\', '\\"', '\\n', '\\n', '\\n'], $text);
    }
}
