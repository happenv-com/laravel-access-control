<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionGroupDto;
use Happenv\LaravelAccessControl\Dto\PermissionSubjectDto;
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
            $subject = $reflector->getSubject();

            if (! isset($grouped[$group->getSlug()])) {
                $grouped[$group->getSlug()] = new PermissionGroupDto(
                    name: $group->getName(),
                    slug: $group->getSlug(),
                    children: new Collection,
                    description: $group->getDescription(),
                    subjects: new Collection,
                );
            }

            $grouped[$group->getSlug()]->children = $grouped[$group->getSlug()]->children->concat($subject->children);

            // Keyed by the declaring enum, not by the subject slug: the slug is
            // the class's short name, so two modules sharing a group could both
            // contribute e.g. `settings` and the second would silently replace
            // the first, taking its permissions out of the UI with it.
            $grouped[$group->getSlug()]->subjects->put($subject->enum, $subject);
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

    /**
     * Every subject across every group, keyed by the enum that declares it.
     *
     * @return Collection<class-string<PermissionDefinition>,PermissionSubjectDto>
     */
    public function getSubjects(): Collection
    {
        return $this->getGroupedPermissions()
            ->flatMap(fn (PermissionGroupDto $group): Collection => $group->subjects->values())
            ->mapWithKeys(fn (PermissionSubjectDto $subject): array => [$subject->enum => $subject]);
    }
}
