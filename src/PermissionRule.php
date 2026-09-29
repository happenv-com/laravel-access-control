<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * One rule as DECLARED: which case said it, of which kind, about which permission, with what reason.
 *
 * The reason is kept as written — a translation key or a closure — because rules are compiled once
 * per process, and a translated string would carry the locale of whoever compiled them.
 */
final readonly class PermissionRule
{
    public function __construct(
        public PermissionRuleType $type,
        /** The case that declares the rule — the only one whose answer the rule changes. */
        public PermissionDefinition $permission,
        /** The permission the rule points at. */
        public PermissionDefinition $other,
        public string | Closure | null $reason = null,
    ) {}
}
