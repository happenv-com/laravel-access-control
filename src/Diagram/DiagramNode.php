<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

final readonly class DiagramNode
{
    public function __construct(
        /** Stable within a diagram: `principal`, `direct`, `role:<n>`, `permission:<value>`. */
        public string $id,
        public NodeKind $kind,
        public string $label,
        public ?PermissionDefinition $permission = null,
        public ?PermissionState $state = null,
        /** The id of the cluster the node sits in, if any. */
        public ?string $cluster = null,
    ) {}

    /**
     * @return array{id: string, kind: string, label: string, permission: int|string|null, state: string|null, cluster: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'permission' => $this->permission?->value,
            'state' => $this->state?->value,
            'cluster' => $this->cluster,
        ];
    }
}
