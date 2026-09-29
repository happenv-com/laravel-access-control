<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use BackedEnum;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionConditions;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;

/**
 * @phpstan-ignore trait.unused
 */
trait HasRolesAndPermissions
{
    use HasPermissions {
        HasPermissions::hasPermissionTo as hasDirectPermissionTo;
        // Both traits bring the same list, asked through this class's hasPermissionTo().
        HasPermissions::getEffectivePermissions insteadof HasRoles;
    }
    use HasRoles {
        HasRoles::hasPermissionTo as hasRolePermissionTo;
    }

    /**
     * Resolved ONCE over the union of the direct grants and the roles' grants, then restricted.
     *
     * Asking the two sources separately — as this did before rules existed — would split a rule
     * across them: a requirement stored directly would not count for a permission a role stores.
     * Not memoised, as the direct part never was, since `givePermissionTo()` changes it in place; the
     * roles' grants are, by {@see HasRoles}.
     */
    public function hasPermissionTo($permission): bool
    {
        if (! $permission instanceof PermissionDefinition) {
            // An ability string, or a backed enum that is not a permission — read by its value, as
            // HasRoles reads it. Neither rules nor restrictions apply to one — but the conditions of
            // the REGISTERED permission it names, if any, still do (see HasRoles::hasPermissionTo()).
            $ability = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

            $granted = $this->getPermissions()->contains($ability)
                || $this->hasRolePermissionTo($permission);

            if (! $granted) {
                return false;
            }

            $definition = $this->registeredPermission($ability);

            return $definition === null || resolve(PermissionConditions::class)->metBy($definition, $this);
        }

        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        $direct = $this->getPermissions();

        return resolve(PermissionResolver::class)->allows(
            $permission,
            fn (PermissionDefinition $candidate): bool => $direct->contains($candidate->value) || $this->hasRoleGrant($candidate),
        ) && resolve(PermissionConditions::class)->metBy($permission, $this);
    }
}
