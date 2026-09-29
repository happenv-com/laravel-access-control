<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

final readonly class DiagramEdge
{
    public function __construct(
        public string $from,
        public string $to,
        public EdgeKind $kind,
        /** The rule's reason, translated; null when none was declared. */
        public ?string $label = null,
    ) {}

    /**
     * @return array{from: string, to: string, kind: string, label: string|null}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'kind' => $this->kind->value,
            'label' => $this->label,
        ];
    }
}
