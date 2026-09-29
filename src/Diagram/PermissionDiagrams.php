<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Diagram\Renderer\DiagramRendererRegistry;
use Happenv\LaravelAccessControl\Diagram\Renderer\UnsupportedDiagramFormatException;

/**
 * Where an application draws permissions: build a diagram, then render it in any registered format
 * — or take its `toArray()` and draw it yourself.
 */
final readonly class PermissionDiagrams
{
    public function __construct(
        private CatalogueDiagramBuilder $catalogue,
        private PrincipalDiagramBuilder $principal,
        private DiagramRendererRegistry $renderers,
    ) {}

    public function catalogue(): PermissionDiagram
    {
        return $this->catalogue->build();
    }

    public function forPrincipal(AuthControllable $principal): PermissionDiagram
    {
        return $this->principal->build($principal);
    }

    /**
     * @throws UnsupportedDiagramFormatException
     */
    public function render(PermissionDiagram $diagram, string $format): string
    {
        return $this->renderers->get($format)->render($diagram);
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return $this->renderers->formats();
    }
}
