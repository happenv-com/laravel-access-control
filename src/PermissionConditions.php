<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Contracts\Auth\Authenticatable;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnumUnitCase;

/**
 * The conditions declared on permissions: every attribute implementing {@see PermissionCondition},
 * found by that interface — on the enum, for every case, and on the case, adding to the enum's.
 *
 * The attribute INSTANCES are kept for the life of the process: they are facts about the code, which
 * does not change at runtime, so this is safe under Octane. Their ANSWERS are never kept.
 */
final class PermissionConditions
{
    /**
     * @var array<string, list<PermissionCondition>>
     */
    private array $declared = [];

    /**
     * Every condition of the permission, the enum's first. The account must meet all of them.
     *
     * @return list<PermissionCondition>
     */
    public function for(PermissionDefinition $permission): array
    {
        return $this->declared[$permission::class . '::' . $permission->name] ??= $this->read($permission);
    }

    /**
     * The conditions of the permission the principal fails — none for a principal that is not
     * `Authenticatable`, which is never evaluated against conditions.
     *
     * @return list<PermissionCondition>
     */
    public function unmet(PermissionDefinition $permission, object $principal): array
    {
        if (! $principal instanceof Authenticatable) {
            return [];
        }

        return array_values(array_filter(
            $this->for($permission),
            fn (PermissionCondition $condition): bool => ! $condition->check($permission, $principal),
        ));
    }

    /**
     * Whether the principal meets every condition of the permission — asking no further than the
     * first it fails. Always true for a principal that is not `Authenticatable`.
     */
    public function metBy(PermissionDefinition $permission, object $principal): bool
    {
        if (! $principal instanceof Authenticatable) {
            return true;
        }

        foreach ($this->for($permission) as $condition) {
            if (! $condition->check($permission, $principal)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<PermissionCondition>
     */
    private function read(PermissionDefinition $permission): array
    {
        $attributes = [
            ...(new ReflectionClass($permission))->getAttributes(PermissionCondition::class, ReflectionAttribute::IS_INSTANCEOF),
            ...(new ReflectionEnumUnitCase($permission, $permission->name))->getAttributes(PermissionCondition::class, ReflectionAttribute::IS_INSTANCEOF),
        ];

        return array_map(
            fn (ReflectionAttribute $attribute): PermissionCondition => $attribute->newInstance(),
            $attributes,
        );
    }
}
