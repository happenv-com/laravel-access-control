<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer;

use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;

/**
 * The diagram's array, as JSON — a machine contract, versioned by its `schema`.
 */
final class JsonRenderer implements DiagramRenderer
{
    public function format(): string
    {
        return 'json';
    }

    public function render(PermissionDiagram $diagram): string
    {
        return json_encode(
            $diagram->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
    }
}
