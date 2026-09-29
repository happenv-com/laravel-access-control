<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionRuleType;

/**
 * The registered permissions as the diagrams draw them: each with its translated name, its group
 * and subject clusters, and the rules it declares.
 *
 * @internal
 */
final class DiagramCatalogue
{
    /**
     * @var array<array-key, PermissionDto> in catalogue order
     */
    private array $permissions = [];

    /**
     * @var array<array-key, array{DiagramCluster, DiagramCluster}> the group and subject of each permission
     */
    private array $clusters = [];

    public function __construct(PermissionCollection $collection)
    {
        foreach ($collection->getGroupedPermissions() as $group) {
            $groupCluster = new DiagramCluster('group:' . $group->slug, $group->name);

            foreach ($group->subjects as $subject) {
                $subjectCluster = new DiagramCluster('subject:' . $subject->enum, $subject->name, $groupCluster->id);

                foreach ($subject->children as $permission) {
                    $this->permissions[$permission->slug] = $permission;
                    $this->clusters[$permission->slug] = [$groupCluster, $subjectCluster];
                }
            }
        }
    }

    /**
     * The node id of a permission — or of a stored value, which may belong to no registered enum.
     */
    public static function id(PermissionDefinition | int | string $permission): string
    {
        return 'permission:' . ($permission instanceof PermissionDefinition ? $permission->value : $permission);
    }

    /**
     * @return array<array-key, PermissionDto>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function has(PermissionDefinition $permission): bool
    {
        return ($this->permissions[$permission->value] ?? null)?->enum === $permission;
    }

    /**
     * Add the permission's node, and the clusters it sits in. A permission outside the catalogue
     * sits in no cluster and is labelled with its value — nothing else is known of it.
     */
    public function addPermission(DiagramDraft $draft, PermissionDefinition $permission, ?PermissionState $state): void
    {
        $cluster = null;
        $label = (string) $permission->value;

        if ($this->has($permission)) {
            [$group, $subject] = $this->clusters[$permission->value];

            $draft->addCluster($group);
            $draft->addCluster($subject);

            $cluster = $subject->id;
            $label = $this->permissions[$permission->value]->name;
        }

        $draft->addNode(new DiagramNode(self::id($permission), NodeKind::Permission, $label, $permission, $state, $cluster));
    }

    /**
     * Every rule a drawn permission declares, as an edge — when both of its ends are drawn.
     */
    public function addRuleEdges(DiagramDraft $draft): void
    {
        foreach ($this->permissions as $permission) {
            $id = self::id($permission->enum);

            if (! $draft->hasNode($id)) {
                continue;
            }

            foreach ($permission->rules as $rule) {
                $other = self::id($rule->other);

                if ($rule->permission !== $permission->enum || ! $draft->hasNode($other)) {
                    continue;
                }

                $draft->addEdge(match ($rule->type) {
                    PermissionRuleType::Requires => new DiagramEdge($id, $other, EdgeKind::Requires, $rule->reason),
                    PermissionRuleType::ImpliedBy => new DiagramEdge($other, $id, EdgeKind::Implies, $rule->reason),
                    PermissionRuleType::ConflictsWith => new DiagramEdge($id, $other, EdgeKind::ConflictsWith, $rule->reason),
                });
            }
        }
    }

    /**
     * @return list<PermissionDefinition> what the permission declares rules of the given kinds about
     */
    public function declaredTargets(PermissionDefinition $permission, PermissionRuleType ...$types): array
    {
        if (! $this->has($permission)) {
            return [];
        }

        $targets = [];

        foreach ($this->permissions[$permission->value]->rules as $rule) {
            if ($rule->permission === $permission && in_array($rule->type, $types, true)) {
                $targets[] = $rule->other;
            }
        }

        return $targets;
    }
}
