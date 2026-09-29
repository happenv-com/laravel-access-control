<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer;

use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;

/**
 * Turns any diagram into one text format. Tag an implementation with
 * {@see DiagramRendererRegistry::TAG} to offer a format, or to replace a built-in one.
 */
interface DiagramRenderer
{
    public function format(): string;

    public function render(PermissionDiagram $diagram): string;
}
