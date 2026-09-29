<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * The case this sits on is effective only while `$permission` is effective.
 *
 * Declared on the DEPENDENT permission, pointing at what it needs; several on one case mean all of
 * them are needed. Like every rule it changes only the answer for the case it sits on: it narrows
 * that case and never touches `$permission`.
 *
 * `$reason` is for a permission-management UI and is never read at runtime: a translation key or
 * text, passed through `__()` with `:permission` (this case's name) and `:other` (`$permission`'s),
 * or — from PHP 8.5, which accepts a static closure as an attribute argument — a
 * `Closure(string $permission, string $other): string`.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class Requires
{
    public function __construct(
        public PermissionDefinition $permission,
        public string | Closure | null $reason = null,
    ) {}
}
