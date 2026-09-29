<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;

/**
 * @phpstan-ignore trait.unused
 */
trait HasRolesAndPermissions
{
    use HasPermissions {
        HasPermissions::hasPermissionTo as hasDirectPermissionTo;
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
            // An ability string: neither rules nor restrictions apply to one.
            return $this->getPermissions()->contains((string) $permission)
                || $this->hasRolePermissionTo($permission);
        }

        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        $direct = $this->getPermissions();

        return resolve(PermissionResolver::class)->allows(
            $permission,
            fn (PermissionDefinition $candidate): bool => $direct->contains($candidate->value) || $this->hasRoleGrant($candidate),
        );
    }
}
