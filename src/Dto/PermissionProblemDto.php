<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionProblemType;
use Happenv\LaravelAccessControl\PermissionRuleType;

/**
 * One declaration problem as data — for a UI that words it in its own language and marks the rows
 * involved.
 */
final readonly class PermissionProblemDto
{
    public function __construct(
        public PermissionProblemType $type,
        /** The permission whose declaration is wrong: the one that can never be allowed, or whose rule points nowhere. */
        public PermissionDefinition $permission,
        /** The permission the problem is about: the one it conflicts with, or the unregistered target. */
        public PermissionDefinition $other,
        /** The rule at fault: `ConflictsWith` for a conflict, the rule's own type for an unregistered target. */
        public PermissionRuleType $ruleType,
    ) {}
}
