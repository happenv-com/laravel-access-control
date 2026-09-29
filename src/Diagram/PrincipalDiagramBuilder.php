<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Closure;
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\PermissionRestrictions;
use Happenv\LaravelAccessControl\PermissionRuleType;
use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Happenv\LaravelAccessControl\Traits\HasRoles;

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
    ) {}

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

            foreach ($this->registered($catalogue, $principal->getGrants()) as $permission) {
                $stored[$permission->value] = true;
                $structural[] = new DiagramEdge('direct', DiagramCatalogue::id($permission), EdgeKind::Stores);
            }
        }

        if (isset($traits[HasRoles::class]) && method_exists($principal, 'getRoles')) {
            $readable = true;
            $index = 0;

            foreach ($principal->getRoles() as $role) {
                $id = 'role:' . $index++;

                $draft->addNode(new DiagramNode($id, NodeKind::Role, GrantHolderName::of($role)));
                $structural[] = new DiagramEdge('principal', $id, EdgeKind::Holds);

                foreach ($this->held($catalogue, $role) as [$permission, $kind]) {
                    $stored[$permission->value] = true;
                    $structural[] = new DiagramEdge($id, DiagramCatalogue::id($permission), $kind);
                }
            }
        }

        // Without either trait nothing stored can be read, and what the principal answers is all
        // there is to draw.
        $isStored = $readable
            ? fn (PermissionDefinition $permission): bool => isset($stored[$permission->value])
            : $principal->hasPermissionTo(...);

        foreach ($this->shown($catalogue, $principal, $stored) as $permission) {
            $catalogue->addPermission($draft, $permission, $this->state($principal, $permission, $isStored));
        }

        foreach ($structural as $edge) {
            $draft->addEdge($edge);
        }

        $catalogue->addRuleEdges($draft);

        return $draft->toDiagram();
    }

    /**
     * What a role holds, and how the diagram knows: a {@see HoldsGrants} role hands over what it
     * stores; any other role is asked, permission by permission, whether it may act.
     *
     * @return list<array{PermissionDefinition, EdgeKind}>
     */
    private function held(DiagramCatalogue $catalogue, AuthControllable $role): array
    {
        if ($role instanceof HoldsGrants) {
            return array_map(
                fn (PermissionDefinition $permission): array => [$permission, EdgeKind::Stores],
                $this->registered($catalogue, $role->getGrants()),
            );
        }

        $held = [];

        foreach ($catalogue->permissions() as $permission) {
            if ($role->hasPermissionTo($permission->enum)) {
                $held[] = [$permission->enum, EdgeKind::Grants];
            }
        }

        return $held;
    }

    /**
     * The registered permissions among stored values, in catalogue order. A value nobody registered
     * is not in the catalogue, and there is nothing to draw it with.
     *
     * @param  iterable<string>  $values
     * @return list<PermissionDefinition>
     */
    private function registered(DiagramCatalogue $catalogue, iterable $values): array
    {
        $wanted = [];

        foreach ($values as $value) {
            $wanted[(string) $value] = true;
        }

        $registered = [];

        foreach ($catalogue->permissions() as $value => $permission) {
            if (isset($wanted[(string) $value])) {
                $registered[] = $permission->enum;
            }
        }

        return $registered;
    }

    /**
     * The permissions that concern the principal, in catalogue order: what it stores or may act
     * on, and what those require or conflict with — so a missing requirement and a lost conflict
     * have a node to point at. A target outside the catalogue comes last.
     *
     * @param  array<array-key, true>  $stored
     * @return list<PermissionDefinition>
     */
    private function shown(DiagramCatalogue $catalogue, AuthControllable $principal, array $stored): array
    {
        $concerned = [];

        foreach ($catalogue->permissions() as $value => $permission) {
            if (isset($stored[$value]) || $principal->hasPermissionTo($permission->enum)) {
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
        if ($principal->hasPermissionTo($permission)) {
            return $isStored($permission) ? PermissionState::Allowed : PermissionState::Implied;
        }

        $resolution = $this->resolver->explain($permission, $isStored);

        if ($resolution->allowed) {
            return $this->restrictions->isRestricted($permission) ? PermissionState::Restricted : PermissionState::Denied;
        }

        if (! $resolution->granted) {
            return PermissionState::NotGranted;
        }

        // Granted and not allowed: a requirement is missing, or — every requirement active — a
        // conflict is lost.
        return $resolution->missing !== [] ? PermissionState::MissingRequirement : PermissionState::Conflict;
    }
}
