<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer;

use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\NodeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;

/**
 * An indented tree for a terminal.
 *
 * A principal's diagram is walked from the principal along what it holds and what that stores;
 * each permission lists its rules the first time it appears. The permissions the walk does not
 * reach — implied, or drawn as a rule's target — follow under `not stored`, in their clusters.
 * A catalogue is its clusters.
 */
final class TreeRenderer implements DiagramRenderer
{
    public function format(): string
    {
        return 'tree';
    }

    public function render(PermissionDiagram $diagram): string
    {
        $printed = [];
        $roots = [];

        foreach ($diagram->nodes as $node) {
            if ($node->kind === NodeKind::Principal) {
                $roots[] = $this->walk($diagram, $node, '', $printed);
            }
        }

        $rest = $this->section($diagram, null, $printed);

        if ($diagram->kind === DiagramKind::Principal) {
            if ($rest !== []) {
                $roots[] = ['not stored', $rest];
            }
        } else {
            $roots = [...$roots, ...$rest];
        }

        return implode("\n", $this->lines($roots, null));
    }

    /**
     * @param  array<string, true>  $printed
     * @return array{string, list<mixed>}
     */
    private function walk(PermissionDiagram $diagram, DiagramNode $node, string $suffix, array &$printed): array
    {
        if ($node->kind === NodeKind::Permission) {
            $first = ! isset($printed[$node->id]);
            $printed[$node->id] = true;

            return [$this->permission($node) . $suffix, $first ? $this->rules($diagram, $node) : []];
        }

        $children = [];

        foreach ($diagram->edgesFrom($node->id) as $edge) {
            $target = $diagram->node($edge->to);

            if (! $edge->kind->isStructural() || ! $target instanceof DiagramNode) {
                continue;
            }

            $children[] = $this->walk($diagram, $target, $edge->kind === EdgeKind::Grants ? ' (may act)' : '', $printed);
        }

        return [$node->label, $children];
    }

    /**
     * The permissions not printed yet, in the given cluster: those directly in it, then its child
     * clusters that hold any.
     *
     * @param  array<string, true>  $printed
     * @return list<array{string, list<mixed>}>
     */
    private function section(PermissionDiagram $diagram, ?string $cluster, array &$printed): array
    {
        $items = [];

        foreach ($diagram->nodesIn($cluster) as $node) {
            if ($node->kind !== NodeKind::Permission || isset($printed[$node->id])) {
                continue;
            }

            $printed[$node->id] = true;
            $items[] = [$this->permission($node), $this->rules($diagram, $node)];
        }

        foreach ($diagram->clustersIn($cluster) as $child) {
            $children = $this->section($diagram, $child->id, $printed);

            if ($children !== []) {
                $items[] = [$child->label, $children];
            }
        }

        return $items;
    }

    /**
     * The rules touching a permission, from its side: what it requires, implies and conflicts
     * with, and what implies it.
     *
     * @return list<array{string, list<mixed>}>
     */
    private function rules(PermissionDiagram $diagram, DiagramNode $node): array
    {
        $rules = [];

        foreach ($diagram->edges as $edge) {
            $text = match (true) {
                $edge->from === $node->id && $edge->kind === EdgeKind::Requires => 'requires ' . $this->name($diagram, $edge->to),
                $edge->from === $node->id && $edge->kind === EdgeKind::Implies => 'implies ' . $this->name($diagram, $edge->to),
                $edge->to === $node->id && $edge->kind === EdgeKind::Implies => 'implied by ' . $this->name($diagram, $edge->from),
                $edge->from === $node->id && $edge->kind === EdgeKind::ConflictsWith => 'conflicts with ' . $this->name($diagram, $edge->to),
                default => null,
            };

            if ($text !== null) {
                $rules[] = [$edge->label === null ? $text : $text . ': ' . $edge->label, []];
            }
        }

        return $rules;
    }

    private function permission(DiagramNode $node): string
    {
        $value = $node->permission === null ? null : (string) $node->permission->value;
        $text = $value === null || $value === $node->label ? $node->label : sprintf('%s (%s)', $node->label, $value);

        return $node->state === null ? $text : sprintf('%s [%s]', $text, str_replace('-', ' ', $node->state->value));
    }

    private function name(PermissionDiagram $diagram, string $id): string
    {
        return $diagram->node($id)?->label ?? $id;
    }

    /**
     * A root line stands alone; every line below it gets a box-drawing connector.
     *
     * @param  list<array{string, list<mixed>}>  $items
     * @return list<string>
     */
    private function lines(array $items, ?string $prefix): array
    {
        $lines = [];
        $last = count($items) - 1;

        foreach ($items as $index => [$text, $children]) {
            if ($prefix === null) {
                $lines[] = $text;
                array_push($lines, ...$this->lines($children, ''));

                continue;
            }

            $isLast = $index === $last;
            $lines[] = $prefix . ($isLast ? '└── ' : '├── ') . $text;
            array_push($lines, ...$this->lines($children, $prefix . ($isLast ? '    ' : '│   ')));
        }

        return $lines;
    }
}
