<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Traits;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
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
     * Whether the permission is granted AND not withheld by a runtime restriction.
     *
     * A restriction answers `false` without touching the stored grants — withheld, not revoked —
     * so the permission is back the moment the restriction ends.
     */
    public function hasPermissionTo(PermissionDefinition $permission): bool
    {
        if (resolve(PermissionRestrictions::class)->isRestricted($permission)) {
            return false;
        }

        return $this->getPermissions()->contains($permission->value);
    }

    /**
     * Store a direct grant, once.
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

    public function revokePermissionTo(PermissionDefinition $permission): void
    {
        $this->setPermissions($this->getPermissions()->filter(fn (string $perm): bool => $perm !== $permission->value)->values());
    }

    abstract protected function getPermissions(): Collection;

    abstract protected function setPermissions(Collection $permissions): void;
}
