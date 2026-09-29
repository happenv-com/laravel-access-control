<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Closure;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionConditions;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;
use Happenv\LaravelAccessControl\PermissionRuleType;
use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Happenv\LaravelAccessControl\Traits\HasRoles;
use InvalidArgumentException;

/**
 * What one principal may do and why: the principal, what it holds — its direct grants, its roles —
 * what each of those stores, and the rules that turn that into what is effective.
 *
 * The package's traits are recognised by NAME, through `class_uses_recursive()`: a method called
 * `getRoles()` or `getGrants()` on any class could return anything. The principal's own
 * `hasPermissionTo()` decides what is effective; the rules only say why.
 */
final readonly class PrincipalDiagramBuilder
{
    public function __construct(
        private PermissionCollection $collection,
        private PermissionResolver $resolver,
        private PermissionRestrictions $restrictions,
        private PermissionConditions $conditions,
    ) {}

    /**
     * @throws InvalidArgumentException for a role that cannot be asked what it holds
     */
    public function build(AuthControllable $principal): PermissionDiagram
    {
        $catalogue = new DiagramCatalogue($this->collection);
        $draft = new DiagramDraft(DiagramKind::Principal);
        $traits = class_uses_recursive($principal);
        $readable = false;
        $stored = [];
        $structural = [];

        $draft->addNode(new DiagramNode('principal', NodeKind::Principal, GrantHolderName::of($principal)));

        if (isset($traits[HasPermissions::class]) && method_exists($principal, 'getGrants')) {
            $readable = true;
            $draft->addNode(new DiagramNode('direct', NodeKind::Direct, 'direct grants'));
            $structural[] = new DiagramEdge('principal', 'direct', EdgeKind::Holds);

            foreach ($this->ordered($catalogue, $principal->getGrants()) as $value) {
                $stored[$value] = true;
                $structural[] = new DiagramEdge('direct', DiagramCatalogue::id($value), EdgeKind::Stores);
            }
        }

        if (isset($traits[HasRoles::class]) && method_exists($principal, 'getRoles')) {
            $readable = true;
            $position = 0;

            foreach ($principal->getRoles() as $role) {
                $id = 'role:' . $position;
                $held = $this->held($catalogue, $role, $position++, $principal);

                $draft->addNode(new DiagramNode($id, NodeKind::Role, GrantHolderName::of($role)));
                $structural[] = new DiagramEdge('principal', $id, EdgeKind::Holds);

                foreach ($held as [$value, $kind]) {
                    $stored[$value] = true;
                    $structural[] = new DiagramEdge($id, DiagramCatalogue::id($value), $kind);
                }
            }
        }

        // Without either trait nothing stored can be read, and what the principal answers is all
        // there is to draw.
        $isStored = $readable
            ? fn (PermissionDefinition $permission): bool => isset($stored[$permission->value])
            : $principal->hasPermissionTo(...);

        foreach ($this->shown($catalogue, $principal, $isStored) as $permission) {
            $catalogue->addPermission($draft, $permission, $this->state($principal, $permission, $isStored));
        }

        // A stored value is drawn where its permission is: always for a registered one, and for one
        // nobody registered only when a rule makes it a node.
        foreach ($structural as $edge) {
            if ($draft->hasNode($edge->to)) {
                $draft->addEdge($edge);
            }
        }

        $catalogue->addRuleEdges($draft);

        return $draft->toDiagram();
    }

    /**
     * What a role holds, and how the diagram knows: a {@see HoldsGrants} role hands over what it
     * stores; any other role is asked, permission by permission, whether it may act — so what it
     * withholds, for a restriction or a rule of its own, is not drawn.
     *
     * @return list<array{int|string, EdgeKind}>
     *
     * @throws InvalidArgumentException for a role that cannot be asked
     */
    private function held(DiagramCatalogue $catalogue, mixed $role, int $position, AuthControllable $principal): array
    {
        if ($role instanceof HoldsGrants) {
            return array_map(
                fn (int | string $value): array => [$value, EdgeKind::Stores],
                $this->ordered($catalogue, $role->getGrants()),
            );
        }

        // HasRoles asks its roles by duck typing, so a role need not declare AuthControllable — but
        // it has to answer hasPermissionTo(), or there is nothing to draw it with.
        if (! is_object($role) || ! method_exists($role, 'hasPermissionTo')) {
            throw new InvalidArgumentException(sprintf(
                'Role %d of %s is %s, which does not answer hasPermissionTo(); it cannot be drawn.',
                $position,
                get_debug_type($principal),
                get_debug_type($role),
            ));
        }

        $held = [];

        foreach ($catalogue->permissions() as $permission) {
            if ($role->hasPermissionTo($permission->enum)) {
                $held[] = [$permission->enum->value, EdgeKind::Grants];
            }
        }

        return $held;
    }

    /**
     * Stored values in catalogue order, then the ones nobody registered in the order they came.
     *
     * @param  iterable<int|string>  $values
     * @return list<int|string>
     */
    private function ordered(DiagramCatalogue $catalogue, iterable $values): array
    {
        $given = [];

        foreach ($values as $value) {
            $given[$value] = true;
        }

        $ordered = [];

        foreach (array_keys($catalogue->permissions()) as $value) {
            if (isset($given[$value])) {
                $ordered[] = $value;
                unset($given[$value]);
            }
        }

        return [...$ordered, ...array_keys($given)];
    }

    /**
     * The permissions that concern the principal, in catalogue order: what it stores, may act on
     * or is granted — an implied permission that a missing requirement or a lost conflict blocks is
     * exactly what a diagram has to explain — and what those require or conflict with, so the
     * reason has a node to point at. A target outside the catalogue comes last.
     *
     * @param  Closure(PermissionDefinition): bool  $isStored
     * @return list<PermissionDefinition>
     */
    private function shown(DiagramCatalogue $catalogue, AuthControllable $principal, Closure $isStored): array
    {
        $concerned = [];

        foreach ($catalogue->permissions() as $value => $permission) {
            if (
                $isStored($permission->enum)
                || $principal->hasPermissionTo($permission->enum)
                || $this->resolver->explain($permission->enum, $isStored)->granted
            ) {
                $concerned[$value] = $permission->enum;
            }
        }

        $targets = [];

        foreach ($concerned as $permission) {
            foreach ($catalogue->declaredTargets($permission, PermissionRuleType::Requires, PermissionRuleType::ConflictsWith) as $target) {
                $targets[$target->value] = $target;
            }
        }

        $shown = [];

        foreach ($catalogue->permissions() as $value => $permission) {
            if (isset($concerned[$value]) || isset($targets[$value])) {
                $shown[] = $permission->enum;
            }
        }

        foreach ($targets as $target) {
            if (! $catalogue->has($target)) {
                $shown[] = $target;
            }
        }

        return $shown;
    }

    /**
     * @param  Closure(PermissionDefinition): bool  $isStored
     */
    private function state(AuthControllable $principal, PermissionDefinition $permission, Closure $isStored): PermissionState
    {
        $resolution = $this->resolver->explain($permission, $isStored);
        $acts = $principal->hasPermissionTo($permission);

        // Asked of every account — also one answering hasPermissionTo() itself — as the gate asks.
        $unmet = $this->conditions->unmet($permission, $principal) !== [];

        if ($acts && ! $unmet && ! $this->restrictions->isRestricted($permission)) {
            if ($isStored($permission)) {
                return PermissionState::Allowed;
            }

            // Implied only when the rules say so; otherwise the principal allowed it on its own say.
            return $resolution->allowed ? PermissionState::Implied : PermissionState::Overridden;
        }

        if ($resolution->allowed || $acts) {
            return match (true) {
                $this->restrictions->isRestricted($permission) => PermissionState::Restricted,
                $unmet => PermissionState::UnmetCondition,
                default => PermissionState::Denied,
            };
        }

        if (! $resolution->granted) {
            return PermissionState::NotGranted;
        }

        // Granted and not allowed: a requirement is missing, or — every requirement active — a
        // conflict is lost.
        return $resolution->missing !== [] ? PermissionState::MissingRequirement : PermissionState::Conflict;
    }
}
