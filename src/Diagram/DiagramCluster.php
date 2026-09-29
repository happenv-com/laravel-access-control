<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

final readonly class DiagramCluster
{
    public function __construct(
        /** `group:<slug>` or `subject:<enum class>`. */
        public string $id,
        public string $label,
        public ?string $parent = null,
    ) {}

    /**
     * @return array{id: string, label: string, parent: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'parent' => $this->parent,
        ];
    }
}
