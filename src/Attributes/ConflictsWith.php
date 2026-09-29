<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * The case this sits on is not effective while `$permission` is effective.
 *
 * Only the DECLARING side loses: `$permission` keeps working. That is the ownership rule every rule
 * follows — a module can take away its own permission, never another module's — and it decides the
 * conflict without a "who wins" table. Declare it on both cases to lose both.
 *
 * `$reason` works as on {@see Requires}.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class ConflictsWith
{
    public function __construct(
        public PermissionDefinition $permission,
        public string | Closure | null $reason = null,
    ) {}
}
