<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer;

/**
 * The renderers tagged {@see self::TAG}, by format. A later one of the same format replaces an
 * earlier one, so an application's renderer wins over the package's.
 */
final readonly class DiagramRendererRegistry
{
    public const string TAG = 'access-control.diagram-renderers';

    /**
     * @var array<string, DiagramRenderer>
     */
    private array $renderers;

    /**
     * @param  iterable<DiagramRenderer>  $renderers
     */
    public function __construct(iterable $renderers)
    {
        $byFormat = [];

        foreach ($renderers as $renderer) {
            $byFormat[$renderer->format()] = $renderer;
        }

        $this->renderers = $byFormat;
    }

    /**
     * @throws UnsupportedDiagramFormatException
     */
    public function get(string $format): DiagramRenderer
    {
        return $this->renderers[$format] ?? throw new UnsupportedDiagramFormatException(sprintf(
            'No diagram renderer for format [%s]. Available: %s.',
            $format,
            implode(', ', $this->formats()),
        ));
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->renderers);
    }
}
