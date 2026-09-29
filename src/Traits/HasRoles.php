<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use BackedEnum;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionResolver;
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
     * The same memo for ability strings, kept APART from the enums': an enum is ruled and a string
     * is not, so `'product.create'` and `ProductPermission::Create` can have different answers.
     *
     * @var array<string, bool>
     */
    private array $resolvedRoleAbilities = [];

    /**
     * What the roles STORE, raw, by permission value: filled from every {@see HoldsGrants} role at
     * the first check of a permission enum, and by asking the other roles one permission at a time.
     * Null until that first check.
     *
     * @var array<array-key, bool>|null
     */
    private ?array $roleGrants = null;

    /**
     * The roles that cannot hand over their grants.
     *
     * @var list<AuthControllable>
     */
    private array $rolesWithoutGrants = [];

    /**
     * Whether the permission is effective by the rules between permissions over the union of the
     * roles' grants AND no runtime restriction withholds it.
     *
     * The restriction is asked BEFORE the memo and never stored in it. The memo records what the
     * roles grant, which holds for the life of the instance; a restriction can begin or end within
     * that life (a long request, a queued job holding the model). Memoised, a restricted `false`
     * would outlive the restriction and a remembered `true` would ignore one that began after it.
     *
     * The rules are resolved over the UNION of the roles, never role by role: a requirement one role
     * stores satisfies a permission another role stores, and a conflict between two roles is seen.
     * That takes the roles' raw grants, which only a {@see HoldsGrants} role hands over — any other
     * role answers `hasPermissionTo()` with its own rules applied.
     *
     * Only a permission ENUM is restricted or ruled: both are keyed by {@see PermissionDefinition},
     * and an ability string has no definition to hand them. It is answered by the roles alone.
     */
    public function hasPermissionTo($permission): bool
    {
        if (! $permission instanceof PermissionDefinition) {
            $key = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

            return $this->resolvedRoleAbilities[$key] ??= $this->resolveRolePermission($permission);
        }

        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        // Note: ??= does not re-evaluate a cached `false` (only null/unset).
        return $this->resolvedRolePermissions[(string) $permission->value] ??= resolve(PermissionResolver::class)
            ->allows($permission, $this->hasRoleGrant(...));
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
     * Whether any role STORES the permission — raw, for the resolver to apply the rules over.
     */
    private function hasRoleGrant(PermissionDefinition $permission): bool
    {
        $this->roleGrants ??= $this->loadRoleGrants();

        return $this->roleGrants[$permission->value] ??= $this->askRolesWithoutGrants($permission);
    }

    /**
     * Read every {@see HoldsGrants} role's grants into one set — once per instance, instead of a
     * scan of every role per permission — and set the other roles aside to be asked.
     *
     * @return array<array-key, bool>
     */
    private function loadRoleGrants(): array
    {
        $grants = [];
        $this->rolesWithoutGrants = [];

        foreach ($this->getRoles() as $role) {
            if (! $role instanceof HoldsGrants) {
                $this->rolesWithoutGrants[] = $role;

                continue;
            }

            foreach ($role->getGrants() as $grant) {
                $grants[$grant] = true;
            }
        }

        return $grants;
    }

    private function askRolesWithoutGrants(PermissionDefinition $permission): bool
    {
        foreach ($this->rolesWithoutGrants as $role) {
            if ($role->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the per-instance memo, and the roles' grants with it. Call after the
     * user's roles or permissions change within the same request.
     */
    public function forgetResolvedPermissions(): void
    {
        $this->resolvedRolePermissions = [];
        $this->resolvedRoleAbilities = [];
        $this->roleGrants = null;
        $this->rolesWithoutGrants = [];
    }

    /**
     * @return iterable<AuthControllable>
     */
    abstract public function getRoles(): iterable;
}
