<?php

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagrams;

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

    public function reflectPermission(string $permissionClass): void
    {
        // return $this->permissionRegistry->reflect($permissionClass);
    }

    /**
     * Draw the catalogue, or what a principal may do and why — see {@see PermissionDiagrams}.
     */
    public function diagram(): PermissionDiagrams
    {
        return resolve(PermissionDiagrams::class);
    }
}
