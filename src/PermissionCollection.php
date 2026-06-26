<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionGroupDto;
use Illuminate\Support\Collection;

final readonly class PermissionCollection
{
    public function __construct(
        private PermissionRegistry $registry,
    ) {}

    /**
     * @return Collection<string,PermissionGroupDto>
     */
    public function getGroupedPermissions(): Collection
    {
        $grouped = [];

        foreach ($this->registry->permissionDefinitions as $permissionEnum) {
            $reflector = new PermissionReflector($permissionEnum);
            $group = $reflector->getGroup();
            $values = $reflector->getValues();

            if (! isset($grouped[$group->getSlug()])) {
                $grouped[$group->getSlug()] = new PermissionGroupDto(
                    name: $group->getName(),
                    slug: $group->getSlug(),
                    children: new Collection,
                    description: $group->getDescription(),
                );
            }

            $grouped[$group->getSlug()]->children = $grouped[$group->getSlug()]->children->mergeRecursive($values);
        }

        return new Collection($grouped);
    }

    /**
     * @return Collection<int,PermissionDto>
     */
    public function getPermissions(): Collection
    {
        return $this->getGroupedPermissions()
            ->flatMap(fn (PermissionGroupDto $group): Collection => $group->children);
    }
}
