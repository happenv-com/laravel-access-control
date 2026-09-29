<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use BackedEnum;
use Happenv\LaravelAccessControl\AccessControl;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionConditions;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;
use Illuminate\Support\Collection;

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
     * Whether the resolution under way used a role's answer that a restriction shaped — see
     * {@see self::hasRoleGrant()}. Such a resolution is answered, never remembered.
     */
    private bool $resolutionReadRestriction = false;

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
     * and an ability string has no definition to hand them. It is answered by the roles alone —
     * except for the conditions of the REGISTERED permission it names, if any: the string is still a
     * name for that permission, and a condition on it should not be skippable by asking for it by
     * string instead of by enum.
     *
     * An account — a principal that is `Authenticatable` — must also meet the permission's
     * conditions ({@see PermissionConditions}), asked last and never remembered.
     */
    public function hasPermissionTo($permission): bool
    {
        if (! $permission instanceof PermissionDefinition) {
            $key = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

            if (! $this->roleGrantsAbility($permission)) {
                return false;
            }

            $definition = $this->registeredPermission($key);

            // Asked after the memo and never stored in it, like the enum's condition below.
            return $definition === null || resolve(PermissionConditions::class)->metBy($definition, $this);
        }

        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        // Asked after the memo and never stored in it, like the restriction: an account's state — MFA
        // switched on — can change within the life of this instance.
        return $this->allowedByRoles($permission)
            && resolve(PermissionConditions::class)->metBy($permission, $this);
    }

    /**
     * The registered permission the ability string or non-definition backed enum names, if any.
     */
    private function registeredPermission(string $key): ?PermissionDefinition
    {
        return resolve(PermissionRegistry::class)->permissions[$key] ?? null;
    }

    /**
     * Whether the rules allow the permission over the union of the roles' grants — remembered per
     * instance, unless a restriction shaped the answer (see {@see self::hasRoleGrant()}).
     */
    private function allowedByRoles(PermissionDefinition $permission): bool
    {
        $key = (string) $permission->value;

        if (isset($this->resolvedRolePermissions[$key])) {
            return $this->resolvedRolePermissions[$key];
        }

        $this->resolutionReadRestriction = false;

        $allowed = resolve(PermissionResolver::class)->allows($permission, $this->hasRoleGrant(...));

        if (! $this->resolutionReadRestriction) {
            $this->resolvedRolePermissions[$key] = $allowed;
        }

        return $allowed;
    }

    /**
     * A {@see HoldsGrants} role's `hasPermissionTo()` is typed to a {@see PermissionDefinition} — it
     * cannot be asked an ability string — so such a role is read by its raw grants instead; any other
     * role is asked directly, by duck typing, as the enum path asks it.
     */
    /**
     * Whether a role grants an ability string (or a backed enum that is not a permission) — the
     * grant alone, remembered per instance; the caller applies the conditions.
     */
    private function roleGrantsAbility($permission): bool
    {
        $key = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

        return $this->resolvedRoleAbilities[$key] ??= $this->resolveRolePermission($permission);
    }

    private function resolveRolePermission($permission): bool
    {
        $key = $permission instanceof BackedEnum ? (string) $permission->value : (string) $permission;

        foreach ($this->getRoles() as $role) {
            if ($role instanceof HoldsGrants) {
                foreach ($role->getGrants() as $grant) {
                    if ((string) $grant === $key) {
                        return true;
                    }
                }

                continue;
            }

            // A role using HasPermissions answers only a permission enum — asked a string, it threw a
            // TypeError. Its stored grants are read instead: a string is neither ruled nor restricted.
            if (isset(class_uses_recursive($role)[HasPermissions::class]) && method_exists($role, 'getGrants')) {
                foreach ($role->getGrants() as $grant) {
                    if ((string) $grant === $key) {
                        return true;
                    }
                }

                continue;
            }

            if ($role->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any role STORES the permission — raw, for the resolver to apply the rules over.
     *
     * A role that is not {@see HoldsGrants} can only say whether it may ACT, with its own rules and
     * any restriction applied, and its answer stands in for what it stores. For a restricted
     * permission that answer is the restriction's, not the grant's: it is used, and remembered
     * neither here nor in the answer it helps decide — the restriction may end within the life of
     * this instance, and a conflict it hid would stay hidden.
     */
    private function hasRoleGrant(PermissionDefinition $permission): bool
    {
        $this->roleGrants ??= $this->loadRoleGrants();

        if (isset($this->roleGrants[$permission->value])) {
            return $this->roleGrants[$permission->value];
        }

        $answer = $this->askRolesWithoutGrants($permission);

        if ($answer === null) {
            // Only HoldsGrants roles: the set read from them is the whole truth.
            return $this->roleGrants[$permission->value] = false;
        }

        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            $this->resolutionReadRestriction = true;

            return $answer;
        }

        return $this->roleGrants[$permission->value] = $answer;
    }

    /**
     * Read every {@see HoldsGrants} role's grants into one set — once per instance, instead of a
     * scan of every role per permission.
     *
     * @return array<array-key, bool>
     */
    private function loadRoleGrants(): array
    {
        $grants = [];

        foreach ($this->getRoles() as $role) {
            if ($role instanceof HoldsGrants) {
                foreach ($role->getGrants() as $grant) {
                    $grants[$grant] = true;
                }
            }
        }

        return $grants;
    }

    /**
     * Ask every role that is not {@see HoldsGrants}, from the roles as they are NOW — as every
     * uncached permission always did, so an application that declares no rules sees no change.
     *
     * @return bool|null null when no such role was there to ask
     */
    private function askRolesWithoutGrants(PermissionDefinition $permission): ?bool
    {
        $asked = false;

        foreach ($this->getRoles() as $role) {
            if ($role instanceof HoldsGrants) {
                continue;
            }

            if ($role->hasPermissionTo($permission)) {
                return true;
            }

            $asked = true;
        }

        return $asked ? false : null;
    }

    /**
     * Every registered permission this principal may act on now — rules and restrictions applied,
     * voters not. See {@see AccessControl::effectivePermissions()}.
     *
     * @return Collection<int, PermissionDefinition>
     */
    public function getEffectivePermissions(): Collection
    {
        return resolve(AccessControl::class)->effectivePermissions($this);
    }

    /**
     * Clear the per-instance memo, and the roles' grants with it. Call after the
     * user's roles, or the grants of one of its roles, change within the same request.
     */
    public function forgetResolvedPermissions(): void
    {
        $this->resolvedRolePermissions = [];
        $this->resolvedRoleAbilities = [];
        $this->roleGrants = null;
    }

    /**
     * @return iterable<AuthControllable>
     */
    abstract public function getRoles(): iterable;
}
