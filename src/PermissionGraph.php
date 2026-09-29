<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Exceptions\InvalidPermissionRuleException;

/**
 * Every rule declared by every registered permission enum, indexed for the checks that read them.
 *
 * KEPT FOR THE LIFE OF THE PROCESS, unlike {@see PermissionRestrictions}: rules are facts about the
 * code, not conditions, so what one request compiled is still true for the next — under Octane too.
 * Compiled LAZILY, because modules register in their own `boot()` and only the first check knows
 * they are done, and again whenever the registry has grown since, which is the only way it changes.
 *
 * Holds no principal's state: resolving a principal's permissions is {@see PermissionResolver}'s.
 */
final class PermissionGraph
{
    /**
     * How many enums the registry held when this was compiled; -1 before the first compilation.
     */
    private int $compiledFor = -1;

    /**
     * Every permission that declares at least one rule, keyed by value.
     *
     * @var array<array-key, PermissionDefinition>
     */
    private array $declaring = [];

    /**
     * @var array<array-key, list<PermissionDefinition>>
     */
    private array $requirements = [];

    /**
     * @var array<array-key, list<PermissionDefinition>>
     */
    private array $directImpliers = [];

    /**
     * @var array<array-key, list<PermissionDefinition>>
     */
    private array $impliers = [];

    /**
     * @var array<array-key, list<PermissionDefinition>>
     */
    private array $conflicts = [];

    /**
     * @var array<array-key, list<PermissionRule>>
     */
    private array $touching = [];

    /**
     * @var list<PermissionRule>
     */
    private array $rules = [];

    public function __construct(
        private readonly PermissionRegistry $registry,
    ) {}

    /**
     * Whether the permission declares any rule. One that does not is answered by what the principal
     * stores — exactly, not approximately: a rule changes only the permission that declares it.
     */
    public function declaresRules(PermissionDefinition $permission): bool
    {
        $this->compile();

        return isset($this->declaring[$permission->value]);
    }

    /**
     * @return list<PermissionDefinition> what the permission declares it `Requires`
     */
    public function requirements(PermissionDefinition $permission): array
    {
        $this->compile();

        return $this->requirements[$permission->value] ?? [];
    }

    /**
     * @return list<PermissionDefinition> what the permission declares itself `ImpliedBy` — one step
     */
    public function directImpliers(PermissionDefinition $permission): array
    {
        $this->compile();

        return $this->directImpliers[$permission->value] ?? [];
    }

    /**
     * impliedBy*: every permission from which this one is reachable through `ImpliedBy`, excluding
     * itself, each once. Precomputed, so a check iterates a set and never walks an edge.
     *
     * @return list<PermissionDefinition>
     */
    public function impliers(PermissionDefinition $permission): array
    {
        $this->compile();

        return $this->impliers[$permission->value] ?? [];
    }

    /**
     * @return list<PermissionDefinition> what the permission declares it `ConflictsWith`
     */
    public function conflicts(PermissionDefinition $permission): array
    {
        $this->compile();

        return $this->conflicts[$permission->value] ?? [];
    }

    /**
     * Every rule the permission declares OR is the target of — the same instance on both ends.
     *
     * @return list<PermissionRule>
     */
    public function rulesTouching(PermissionDefinition $permission): array
    {
        $this->compile();

        return $this->touching[$permission->value] ?? [];
    }

    /**
     * Declarations that compile but cannot mean what they say — for an application test to assert
     * empty, the way {@see PermissionReflector::declaresMutatesData()} is asserted.
     *
     * NEVER A FALSE POSITIVE: a permission that can be allowed is never reported, because failing an
     * application's build over a correct catalogue is worse than a miss. It may miss one — a conflict
     * with a permission whose own requirements all follow from the declaring permission's.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $this->compile();

        $problems = [];

        foreach ($this->conflicts as $key => $conflicting) {
            $permission = $this->declaring[$key];
            $required = $this->requirementClosure($permission);

            foreach ($conflicting as $other) {
                if (isset($required[$other->value])) {
                    $problems[] = sprintf(
                        '%s can never be allowed: it requires %s, which it conflicts with.',
                        self::describe($permission),
                        self::describe($other),
                    );
                } elseif (! isset($this->requirements[$other->value]) && in_array($permission, $this->impliers[$other->value] ?? [], true)) {
                    // Certain only while the implied permission needs nothing: granted, it is active.
                    $problems[] = sprintf(
                        '%s can never be allowed: it implies %s, which it conflicts with.',
                        self::describe($permission),
                        self::describe($other),
                    );
                }
            }
        }

        foreach ($this->rules as $rule) {
            if (($this->registry->permissions[$rule->other->value] ?? null) !== $rule->other) {
                $problems[] = sprintf(
                    '%s declares %s about %s, whose enum is not registered.',
                    self::describe($rule->permission),
                    $rule->type->name,
                    self::describe($rule->other),
                );
            }
        }

        return $problems;
    }

    private function compile(): void
    {
        $definitions = count($this->registry->permissionDefinitions);

        if ($this->compiledFor === $definitions) {
            return;
        }

        $this->declaring = [];
        $this->requirements = [];
        $this->directImpliers = [];
        $this->conflicts = [];
        $this->touching = [];
        $this->rules = [];

        foreach ($this->registry->permissionDefinitions as $enum) {
            $reflector = new PermissionReflector($enum);

            foreach ($enum::cases() as $case) {
                foreach ($reflector->getRules($case) as $rule) {
                    $this->index($rule);
                }
            }
        }

        $this->assertRequirementsAcyclic();
        $this->impliers = $this->closeImpliers();

        // Last, so a declaration error thrown above is thrown again by the next check instead of
        // leaving half an index to answer it.
        $this->compiledFor = $definitions;
    }

    private function index(PermissionRule $rule): void
    {
        if ($rule->other === $rule->permission) {
            throw new InvalidPermissionRuleException(sprintf(
                '%s declares %s about itself.',
                self::describe($rule->permission),
                $rule->type->name,
            ));
        }

        $key = $rule->permission->value;

        foreach ($this->touching[$key] ?? [] as $known) {
            if ($known->type === $rule->type && $known->permission === $rule->permission && $known->other === $rule->other) {
                // Declared twice, one rule: every list stays a set.
                return;
            }
        }

        match ($rule->type) {
            PermissionRuleType::Requires => $this->requirements[$key][] = $rule->other,
            PermissionRuleType::ImpliedBy => $this->directImpliers[$key][] = $rule->other,
            PermissionRuleType::ConflictsWith => $this->conflicts[$key][] = $rule->other,
        };

        $this->declaring[$key] = $rule->permission;
        $this->rules[] = $rule;
        $this->touching[$key][] = $rule;
        $this->touching[$rule->other->value][] = $rule;
    }

    /**
     * A cycle of `Requires` would make a check recurse forever. A cycle of `ImpliedBy` is legal:
     * implications are closed into sets, see {@see self::closeImpliers()}.
     *
     * @throws InvalidPermissionRuleException
     */
    private function assertRequirementsAcyclic(): void
    {
        $done = [];

        foreach (array_keys($this->requirements) as $key) {
            $this->visitRequirements($this->declaring[$key], [], $done);
        }
    }

    /**
     * @param  array<array-key, true>  $path  the permissions on the way here, in order
     * @param  array<array-key, true>  $done
     */
    private function visitRequirements(PermissionDefinition $permission, array $path, array &$done): void
    {
        $key = $permission->value;

        if (isset($path[$key])) {
            $cycle = [...array_keys($path), $key];

            throw new InvalidPermissionRuleException(sprintf(
                'Permissions require each other in a cycle: %s.',
                implode(' -> ', array_slice($cycle, (int) array_search($key, $cycle, true))),
            ));
        }

        if (isset($done[$key])) {
            return;
        }

        $path[$key] = true;

        foreach ($this->requirements[$key] ?? [] as $required) {
            $this->visitRequirements($required, $path, $done);
        }

        $done[$key] = true;
    }

    /**
     * impliedBy* of every permission that declares an implication — a set per permission, built
     * with a visited set so a cycle ends where it started.
     *
     * @return array<array-key, list<PermissionDefinition>>
     */
    private function closeImpliers(): array
    {
        $closed = [];

        foreach ($this->directImpliers as $key => $direct) {
            $seen = [$key => true];
            $closure = [];
            $pending = $direct;

            while ($pending !== []) {
                $implier = array_pop($pending);

                if (isset($seen[$implier->value])) {
                    continue;
                }

                $seen[$implier->value] = true;
                $closure[] = $implier;

                array_push($pending, ...($this->directImpliers[$implier->value] ?? []));
            }

            $closed[$key] = $closure;
        }

        return $closed;
    }

    /**
     * requires*: every permission the given one requires, directly or through a requirement.
     *
     * @return array<array-key, true>
     */
    private function requirementClosure(PermissionDefinition $permission): array
    {
        $closure = [];
        $pending = $this->requirements[$permission->value] ?? [];

        while ($pending !== []) {
            $required = array_pop($pending);

            if (isset($closure[$required->value])) {
                continue;
            }

            $closure[$required->value] = true;

            array_push($pending, ...($this->requirements[$required->value] ?? []));
        }

        return $closure;
    }

    private static function describe(PermissionDefinition $permission): string
    {
        return $permission::class . '::' . $permission->name;
    }
}
