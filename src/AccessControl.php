<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagrams;
use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Happenv\LaravelAccessControl\Traits\HasRoles;
use Illuminate\Support\Collection;

class AccessControl
{
    public function __construct(
        private readonly VoterRegistry $voterRegistry,
        private readonly PermissionRegistry $permissionRegistry
    ) {}

    public function registerVoter(string $voterClassOrPermission, ?Closure $voter = null): void
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
     * @param  Closure(PermissionDefinition): bool  $restriction
     */
    public function restrictUsing(Closure $restriction): void
    {
        resolve(PermissionRestrictions::class)->restrictUsing($restriction);
    }

    public function isRestricted(PermissionDefinition $permission): bool
    {
        return resolve(PermissionRestrictions::class)->isRestricted($permission);
    }

    /**
     * Every registered permission the principal may act on now, in registration order: its own
     * `hasPermissionTo()` asked for each, so rules count — and so does whatever a principal
     * answering that method itself (an administrator short-circuit) decides. Restrictions and an
     * account's conditions are applied here too, whoever answered, so the list is what the gate lets
     * through.
     *
     * Voters do not count: they judge an action on a particular object, and a list has none to hand
     * them. A permission nobody registered is not listed — the registry is the only catalogue there is.
     *
     * @return Collection<int, PermissionDefinition>
     */
    public function effectivePermissions(AuthControllable $principal): Collection
    {
        $restrictions = resolve(PermissionRestrictions::class);
        $conditions = resolve(PermissionConditions::class);

        return (new Collection($this->permissionRegistry->permissions))
            ->filter(fn (PermissionDefinition $permission): bool => ! $restrictions->isRestricted($permission)
                && $principal->hasPermissionTo($permission)
                && $conditions->metBy($permission, $principal))
            ->values();
    }

    /**
     * The conditions of the permission the principal fails — none for a principal that is not
     * `Authenticatable`. See {@see PermissionConditions}.
     *
     * @return list<PermissionCondition>
     */
    public function unmetConditions(PermissionDefinition $permission, object $principal): array
    {
        return resolve(PermissionConditions::class)->unmet($permission, $principal);
    }

    /**
     * What the principal STORES, raw — its direct grants and its roles' — for a resolution over it
     * that a UI can put staged changes on top of (see {@see PermissionResolver::explainer()}).
     *
     * The library's traits are recognised by NAME, as the diagrams recognise them: a method called
     * `getGrants()` on any class could return anything. A principal using neither is only asked what
     * it answers.
     *
     * @return Closure(PermissionDefinition): bool
     */
    public function storedGrantsOf(AuthControllable $principal): Closure
    {
        $traits = class_uses_recursive($principal);

        // Checked inline, so static analysis sees getGrants() exists where it is called.
        $direct = isset($traits[HasPermissions::class]) && method_exists($principal, 'getGrants')
            ? $this->valuesOf($principal->getGrants())
            : null;

        // Neither trait: nothing stored can be read, and what the principal answers is all there is.
        if ($direct === null && ! isset($traits[HasRoles::class])) {
            return $principal->hasPermissionTo(...);
        }

        $direct ??= [];
        $roles = $this->roleGrantsOf($principal);

        return fn (PermissionDefinition $permission): bool => isset($direct[$permission->value]) || $roles($permission);
    }

    /**
     * What the principal's ROLES store, read as `HasRoles` reads them: a {@see HoldsGrants} role
     * hands over its raw grants, any other role is asked `hasPermissionTo()` — its own rules and any
     * restriction applied. Nothing for a principal without `HasRoles`.
     *
     * @return Closure(PermissionDefinition): bool
     */
    public function roleGrantsOf(AuthControllable $principal): Closure
    {
        if (! isset(class_uses_recursive($principal)[HasRoles::class]) || ! method_exists($principal, 'getRoles')) {
            return fn (PermissionDefinition $permission): bool => false;
        }

        $stored = [];

        /** @var list<Closure(PermissionDefinition): bool> $asked */
        $asked = [];

        foreach ($principal->getRoles() as $role) {
            if ($role instanceof HoldsGrants) {
                $stored += $this->valuesOf($role->getGrants());
            } elseif (is_object($role) && method_exists($role, 'hasPermissionTo')) {
                // HasRoles asks its roles by duck typing, so a role need not declare AuthControllable.
                $asked[] = $role->hasPermissionTo(...);
            }
        }

        return function (PermissionDefinition $permission) use ($stored, $asked): bool {
            if (isset($stored[$permission->value])) {
                return true;
            }

            foreach ($asked as $asks) {
                if ($asks($permission)) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * @param  iterable<int|string>  $values
     * @return array<array-key, true>
     */
    private function valuesOf(iterable $values): array
    {
        $set = [];

        foreach ($values as $value) {
            $set[$value] = true;
        }

        return $set;
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
