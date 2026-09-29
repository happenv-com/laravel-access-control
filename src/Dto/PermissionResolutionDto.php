<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * Why a permission is or is not in effect — for a UI, which has to say more than `false`.
 *
 * `allowed` is the rules between permissions alone. What else withholds a permission is told apart:
 * a runtime restriction, which withholds it from everyone, and the conditions an account fails.
 * `effective` sums it up — for a principal using the library's traits, what `hasPermissionTo()`
 * answers over the same stored grants.
 */
final readonly class PermissionResolutionDto
{
    /** Allowed by the rules, not restricted, and every condition met. */
    public bool $effective;

    public function __construct(
        /** Effective by the rules. */
        public bool $allowed,
        /** In the grants as given. */
        public bool $stored,
        /** Stored, or stored by anything that implies it. */
        public bool $granted,
        /**
         * The permissions it declares itself implied by that are granted — each by something other
         * than this permission, so a cycle of implications does not name its own members.
         *
         * @var list<PermissionDefinition>
         */
        public array $grantedBy,
        /**
         * The permissions it requires that are not active.
         *
         * @var list<PermissionDefinition>
         */
        public array $missing,
        /**
         * The permissions it conflicts with that are active.
         *
         * @var list<PermissionDefinition>
         */
        public array $conflicting,
        /** A runtime restriction withholds it — from every principal. */
        public bool $restricted = false,
        /**
         * The conditions of it the account fails. Empty when no account was given: a role is never
         * evaluated against conditions.
         *
         * @var list<PermissionCondition>
         */
        public array $unmetConditions = [],
    ) {
        $this->effective = $allowed && ! $restricted && $unmetConditions === [];
    }
}
