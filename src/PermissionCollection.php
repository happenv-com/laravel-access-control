<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionGroupDto;
use Happenv\LaravelAccessControl\Dto\PermissionRuleDto;
use Happenv\LaravelAccessControl\Dto\PermissionSubjectDto;
use Illuminate\Support\Collection;

final readonly class PermissionCollection
{
    private PermissionGraph $graph;

    private PermissionConditions $conditions;

    public function __construct(
        private PermissionRegistry $registry,
        ?PermissionGraph $graph = null,
        ?PermissionConditions $conditions = null,
    ) {
        // Optional, so a collection built over a registry of its own keeps working: its graph has to
        // index THAT registry, which the container's does not.
        $this->graph = $graph ?? new PermissionGraph($registry);

        // Facts about the code, like the rules — a collection of its own may read them afresh.
        $this->conditions = $conditions ?? new PermissionConditions;
    }

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

            $grouped[$group->getSlug()] ??= new PermissionGroupDto(
                name: $group->getName(),
                slug: $group->getSlug(),
                children: new Collection,
                description: $group->getDescription(),
                subjects: new Collection,
            );

            $grouped[$group->getSlug()]->children = $grouped[$group->getSlug()]->children->concat($subject->children);

            // Keyed by the declaring enum, not by the subject slug: the slug is
            // the class's short name, so two modules sharing a group could both
            // contribute e.g. `settings` and the second would silently replace
            // the first, taking its permissions out of the UI with it.
            $grouped[$group->getSlug()]->subjects->put($subject->enum, $subject);
        }

        $this->attachRules($grouped);

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

    /**
     * The slugs DECLARED available on one surface — nothing more.
     *
     * Deliberately not "the slugs this surface offers": a surface that grants everything nobody
     * refused it would get an empty answer here and be right to ignore it. The default belongs to
     * the surface; this method only reports what was written down.
     *
     * @return Collection<int,string>
     */
    public function availableOn(PermissionSurfaceDefinition $surface): Collection
    {
        return $this->getPermissions()
            ->filter(fn (PermissionDto $permission): bool => in_array($surface, $permission->surfaces, true))
            ->map(fn (PermissionDto $permission): string => $permission->slug)
            ->values();
    }

    /**
     * Put every rule on BOTH of its ends, its reason read now — in this request's locale, never in
     * that of whoever compiled the graph — and every condition on its permission.
     *
     * @param  array<string, PermissionGroupDto>  $grouped
     */
    private function attachRules(array $grouped): void
    {
        $permissions = [];

        foreach ($grouped as $group) {
            foreach ($group->children as $permission) {
                $permissions[$permission->slug] = $permission;
            }
        }

        // One DTO per rule, shared by its two ends and translated once.
        $dtos = [];

        foreach ($permissions as $permission) {
            $rules = [];

            foreach ($this->graph->rulesTouching($permission->enum) as $rule) {
                $rules[] = $dtos[spl_object_id($rule)] ??= new PermissionRuleDto(
                    type: $rule->type,
                    permission: $rule->permission,
                    other: $rule->other,
                    reason: $this->reason($rule, $permissions),
                );
            }

            $permission->rules = $rules;
            $permission->conditions = $this->conditions->for($permission->enum);
        }
    }

    /**
     * @param  array<array-key, PermissionDto>  $permissions
     */
    private function reason(PermissionRule $rule, array $permissions): ?string
    {
        if ($rule->reason === null) {
            return null;
        }

        // A rule may point outside the catalogue — at an enum nobody registered — so fall back to
        // the value, which is what is granted anyway.
        $permission = $permissions[$rule->permission->value]->name ?? (string) $rule->permission->value;
        $other = $permissions[$rule->other->value]->name ?? (string) $rule->other->value;

        return $rule->reason instanceof Closure
            ? ($rule->reason)($permission, $other)
            : __($rule->reason, ['permission' => $permission, 'other' => $other]);
    }
}
