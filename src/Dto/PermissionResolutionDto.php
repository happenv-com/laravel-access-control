<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * Why a permission is or is not effective by the rules between permissions — for a UI, which has to
 * say more than `false`.
 *
 * Restrictions are NOT part of it: they are an application condition, and an editor of grants edits
 * grants. `AccessControl::isRestricted()` answers them separately.
 */
final readonly class PermissionResolutionDto
{
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
    ) {}
}
