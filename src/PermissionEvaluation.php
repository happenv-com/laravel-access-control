<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * One resolution against one principal's stored grants, with the memo it needs — which the resolver
 * must not keep itself, since one resolver serves every principal of the process.
 *
 * Each layer reads only the layers before it: an implication reads `stored`, never `active`; a
 * requirement and a conflict read `active`, never `allowed`. That is what keeps the definition free
 * of cycles and negative recursion, and an implementation that lets a rule read a later layer
 * changes what the rules mean.
 *
 * @internal
 */
final class PermissionEvaluation
{
    /**
     * @var array<array-key, bool>
     */
    private array $stored = [];

    /**
     * @var array<array-key, bool>
     */
    private array $granted = [];

    /**
     * @var array<array-key, bool>
     */
    private array $active = [];

    /**
     * @param  Closure(PermissionDefinition): bool  $isStored
     */
    public function __construct(
        private readonly PermissionGraph $graph,
        private readonly Closure $isStored,
    ) {}

    public function stored(PermissionDefinition $permission): bool
    {
        // Note: ??= does not re-evaluate a cached `false` (only null/unset).
        return $this->stored[$permission->value] ??= (bool) ($this->isStored)($permission);
    }

    /**
     * Stored, or stored by anything that implies it. It follows the GRANT, not the activity, of
     * what implies it.
     */
    public function granted(PermissionDefinition $permission): bool
    {
        return $this->granted[$permission->value] ??= $this->resolveGranted($permission);
    }

    /**
     * Granted, and every permission it requires is active.
     */
    public function active(PermissionDefinition $permission): bool
    {
        return $this->active[$permission->value] ??= $this->resolveActive($permission);
    }

    /**
     * Active, and no permission it conflicts with is active. Only the declaring side loses.
     */
    public function allowed(PermissionDefinition $permission): bool
    {
        if (! $this->active($permission)) {
            return false;
        }

        foreach ($this->graph->conflicts($permission) as $conflicting) {
            if ($this->active($conflicting)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the permission is granted by what is stored WITHOUT going through `$excluded` — for
     * naming what grants `$excluded`. In a cycle of implications everything in it is granted the
     * moment one of it is stored, and none of the rest is what grants that one.
     */
    public function grantedAvoiding(PermissionDefinition $permission, PermissionDefinition $excluded): bool
    {
        $seen = [$excluded->value => true];
        $pending = [$permission];

        while ($pending !== []) {
            $candidate = array_pop($pending);

            if (isset($seen[$candidate->value])) {
                continue;
            }

            $seen[$candidate->value] = true;

            if ($this->stored($candidate)) {
                return true;
            }

            array_push($pending, ...$this->graph->directImpliers($candidate));
        }

        return false;
    }

    private function resolveGranted(PermissionDefinition $permission): bool
    {
        if ($this->stored($permission)) {
            return true;
        }

        foreach ($this->graph->impliers($permission) as $implier) {
            if ($this->stored($implier)) {
                return true;
            }
        }

        return false;
    }

    private function resolveActive(PermissionDefinition $permission): bool
    {
        if (! $this->granted($permission)) {
            return false;
        }

        foreach ($this->graph->requirements($permission) as $required) {
            if (! $this->active($required)) {
                return false;
            }
        }

        return true;
    }
}
