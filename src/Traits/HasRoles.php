<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use BackedEnum;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionRestrictions;

/**
 * @phpstan-ignore trait.unused
 */
trait HasRoles
{
    /**
     * Per-instance memo of resolved permission checks. Authorization gates are
     * evaluated many times per request (once per nav item / action), so caching
     * the result avoids re-iterating roles for the same permission. The cache
     * lives on the model instance, which is request-scoped.
     *
     * @var array<string, bool>
     */
    private array $resolvedRolePermissions = [];

    /**
     * Whether any role grants the permission AND no runtime restriction withholds it.
     *
     * The restriction is asked BEFORE the memo and never stored in it. The memo records what the
     * roles grant, which holds for the life of the instance; a restriction can begin or end within
     * that life (a long request, a queued job holding the model). Memoised, a restricted `false`
     * would outlive the restriction and a remembered `true` would ignore one that began after it.
     *
     * Only a permission ENUM is restricted: restrictions are keyed by {@see PermissionDefinition},
     * and an ability string has no definition to hand them.
     */
    public function hasPermissionTo($permission): bool
    {
        if ($permission instanceof PermissionDefinition && resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        $key = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

        // Note: ??= does not re-evaluate a cached `false` (only null/unset).
        return $this->resolvedRolePermissions[$key] ??= $this->resolveRolePermission($permission);
    }

    private function resolveRolePermission($permission): bool
    {
        foreach ($this->getRoles() as $role) {
            if ($role->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the per-instance permission memo. Call after the user's roles or
     * permissions change within the same request.
     */
    public function forgetResolvedPermissions(): void
    {
        $this->resolvedRolePermissions = [];
    }

    /**
     * @return iterable<AuthControllable>
     */
    abstract public function getRoles(): iterable;
}
