<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * Whoever is granted `$permission` is granted the case this sits on as well.
 *
 * Declared on the IMPLIED permission, so a module can widen its own permission by another module's
 * without that module knowing — and can never widen anybody else's. Nothing is stored: take
 * `$permission` away and what it implied goes with it.
 *
 * It follows the GRANT of `$permission`, not its effectiveness: a granted `$permission` implies this
 * case even while one of its own requirements is missing. That is what lets
 * `Update #[Requires(View)]` next to `View #[ImpliedBy(Update)]` resolve without a cycle.
 *
 * `$reason` works as on {@see Requires}.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class ImpliedBy
{
    public function __construct(
        public PermissionDefinition $permission,
        public string | Closure | null $reason = null,
    ) {}
}
