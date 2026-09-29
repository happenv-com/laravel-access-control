<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * @mixin Authenticatable
 *
 * @phpstan-ignore trait.unused
 */
trait HasPermissions
{
    /**
     * Whether the permission is effective by the rules between permissions over the stored grants
     * (see {@see PermissionResolver}) AND not withheld by a runtime restriction.
     *
     * A restriction answers `false` without touching the stored grants — withheld, not revoked —
     * so the permission is back the moment the restriction ends. It applies to the permission asked
     * about only: the rules read the grants, so withholding `Update` does not withhold what `Update`
     * implies.
     */
    public function hasPermissionTo(PermissionDefinition $permission): bool
    {
        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        // Read once per check: the rules may ask about several permissions, and `getPermissions()`
        // builds a new collection every time it is called.
        $grants = $this->getPermissions();

        return resolve(PermissionResolver::class)->allows(
            $permission,
            fn (PermissionDefinition $candidate): bool => $grants->contains($candidate->value),
        );
    }

    /**
     * The stored grants, raw — no rules, no restrictions. A class using this trait that declares
     * {@see HoldsGrants} hands them to the principal holding it, which resolves the rules once over
     * the union of all its roles.
     *
     * @return iterable<string>
     */
    public function getGrants(): iterable
    {
        return $this->getPermissions();
    }

    /**
     * Store a direct grant, once — exactly this permission. Rules never write: nothing it implies is
     * stored with it, so taking it away later takes the implication with it.
     *
     * Deduplicated by what is STORED, never by `hasPermissionTo()`. That check answers a different
     * question — may this principal act now — and says `false` for a stored grant under a
     * restriction (the grant would be pushed again on every call) and, through
     * {@see HasRolesAndPermissions}, `true` for one only a role provides (the direct grant would be
     * skipped and vanish with the role).
     */
    public function givePermissionTo(PermissionDefinition $permission): void
    {
        $permissions = $this->getPermissions();

        if (! $permissions->contains($permission->value)) {
            $this->setPermissions($permissions->push($permission->value));
        }
    }

    /**
     * Remove exactly this grant. A permission something else still implies stays effective.
     */
    public function revokePermissionTo(PermissionDefinition $permission): void
    {
        $this->setPermissions($this->getPermissions()->filter(fn (string $perm): bool => $perm !== $permission->value)->values());
    }

    abstract protected function getPermissions(): Collection;

    abstract protected function setPermissions(Collection $permissions): void;
}
