<?php

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Support\Collection;

class AccessControl
{
    public function __construct(
        private readonly VoterRegistry $voterRegistry,
        private readonly PermissionRegistry $permissionRegistry
    ) {}

    public function registerVoter(string $voterClassOrPermission, ?\Closure $voter = null): void
    {
        $this->voterRegistry->register($voterClassOrPermission, $voter);
    }

    public function registerPermission(string $definitionOrArray): void
    {
        $this->permissionRegistry->register($definitionOrArray);
    }

    /**
     * Withhold permissions from every principal while the closure says so.
     *
     * Resolved at CALL time rather than injected: an instance of this class built by hand would
     * otherwise carry a private set of restrictions that the gate never consults.
     *
     * @param  \Closure(PermissionDefinition): bool  $restriction
     */
    public function restrictUsing(\Closure $restriction): void
    {
        resolve(PermissionRestrictions::class)->restrictUsing($restriction);
    }

    public function isRestricted(PermissionDefinition $permission): bool
    {
        return resolve(PermissionRestrictions::class)->isRestricted($permission);
    }

    /**
     * Every registered permission the principal may act on now, in registration order: its own
     * `hasPermissionTo()` asked for each, so rules and restrictions count — and so does whatever a
     * principal answering that method itself (an administrator short-circuit) decides.
     *
     * Voters do not: they judge an action on a particular object, and a list has none to hand them.
     * A permission nobody registered is not listed — the registry is the only catalogue there is.
     *
     * @return Collection<int, PermissionDefinition>
     */
    public function effectivePermissions(AuthControllable $principal): Collection
    {
        return (new Collection($this->permissionRegistry->permissions))
            ->filter(fn (PermissionDefinition $permission): bool => $principal->hasPermissionTo($permission))
            ->values();
    }

    public function reflectPermission(string $permissionClass): void
    {
        // return $this->permissionRegistry->reflect($permissionClass);
    }
}
