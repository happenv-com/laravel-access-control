<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\PermissionRuleType;

/**
 * A rule between two permissions, as a permission-management UI reads it: the same instance sits on
 * both of its ends, told apart by `$permission`.
 */
final readonly class PermissionRuleDto
{
    public function __construct(
        public PermissionRuleType $type,
        /** The permission that declares the rule — the only one whose answer it changes. */
        public PermissionDefinition $permission,
        /** The permission the rule points at. */
        public PermissionDefinition $other,
        /** Translated in the locale of the request that read it; null when none was declared. */
        public ?string $reason = null,
    ) {}
}
