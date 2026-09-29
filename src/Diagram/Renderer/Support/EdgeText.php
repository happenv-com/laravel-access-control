<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer\Support;

use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;

/**
 * @internal
 */
final class EdgeText
{
    /**
     * The words an edge is drawn with: its kind, then the rule's reason. Null for a structural edge
     * with nothing to say.
     */
    public static function of(DiagramEdge $edge): ?string
    {
        $kind = match ($edge->kind) {
            EdgeKind::Holds, EdgeKind::Stores => null,
            EdgeKind::Grants => 'may act',
            EdgeKind::Requires => 'requires',
            EdgeKind::Implies => 'implies',
            EdgeKind::ConflictsWith => 'conflicts with',
        };

        if ($edge->label === null) {
            return $kind;
        }

        return $kind === null ? $edge->label : $kind . ': ' . $edge->label;
    }
}
