<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionResolutionDto;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether a permission is effective by the rules between permissions, given what a principal stores:
 *
 *     stored ──ImpliedBy──▶ granted ──Requires──▶ active ──ConflictsWith──▶ allowed
 *
 * Restrictions are NOT applied to `allows()`: they belong outermost and to the permission asked about
 * only, so the traits apply them; applied inside, a read-only mode withholding `Update` would also
 * take away what `Update` implies. `explainer()` reports them — and an account's conditions — beside
 * the rules, never inside them.
 *
 * STATELESS, so one instance serves every principal of the process, under Octane too. A resolution's
 * memo lives in a {@see PermissionEvaluation} for one call; anything longer lives on the principal.
 */
final readonly class PermissionResolver
{
    public function __construct(
        private PermissionGraph $graph,
    ) {}

    /**
     * @param  Closure(PermissionDefinition): bool  $stored  whether the principal stores a permission — raw
     */
    public function allows(PermissionDefinition $permission, Closure $stored): bool
    {
        // The fast path, and exact rather than approximate: a rule changes only the permission that
        // declares it, so one that declares nothing is what the principal stores.
        if (! $this->graph->declaresRules($permission)) {
            return (bool) $stored($permission);
        }

        return (new PermissionEvaluation($this->graph, $stored))->allowed($permission);
    }

    /**
     * Why the permission is or is not effective. The closure can describe a form's UNSAVED state as
     * well as a model, which is what a role editor needs.
     *
     * @param  Closure(PermissionDefinition): bool  $stored
     */
    public function explain(PermissionDefinition $permission, Closure $stored): PermissionResolutionDto
    {
        return $this->explainer($stored)($permission);
    }

    /**
     * {@see self::explain()} for as many permissions as asked, over ONE evaluation of the stored
     * state — a permission screen asks permissions × holders times, and each `explain()` starts from
     * nothing.
     *
     * The closure reads each permission of the stored state at most once and keeps the answer: build
     * a new one after the state changes. Given an account, it names the conditions the account fails;
     * without one — a role — it names none.
     *
     * @param  Closure(PermissionDefinition): bool  $stored
     * @return Closure(PermissionDefinition): PermissionResolutionDto
     */
    public function explainer(Closure $stored, ?Authenticatable $principal = null): Closure
    {
        $evaluation = new PermissionEvaluation($this->graph, $stored);

        // Resolved now rather than injected, as AccessControl::restrictUsing() explains.
        $restrictions = resolve(PermissionRestrictions::class);
        $conditions = resolve(PermissionConditions::class);

        return fn (PermissionDefinition $permission): PermissionResolutionDto => new PermissionResolutionDto(
            allowed: $evaluation->allowed($permission),
            stored: $evaluation->stored($permission),
            granted: $evaluation->granted($permission),
            grantedBy: array_values(array_filter(
                $this->graph->directImpliers($permission),
                fn (PermissionDefinition $implier): bool => $evaluation->grantedAvoiding($implier, $permission),
            )),
            missing: array_values(array_filter(
                $this->graph->requirements($permission),
                fn (PermissionDefinition $required): bool => ! $evaluation->active($required),
            )),
            conflicting: array_values(array_filter(
                $this->graph->conflicts($permission),
                $evaluation->active(...),
            )),
            restricted: $restrictions->isRestricted($permission),
            unmetConditions: $principal instanceof Authenticatable ? $conditions->unmet($permission, $principal) : [],
        );
    }
}
