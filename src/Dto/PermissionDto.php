<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;
use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionReflector;

final class PermissionDto
{
    public function __construct(
        public string $name,
        public PermissionDefinition $enum,
        public string $slug,
        public ?string $description = null,
        /**
         * The surfaces this permission was declared available on; empty when nobody declared any.
         *
         * Carries the DECLARATION, never its interpretation: what an empty list means belongs to
         * the surface doing the asking.
         *
         * @var list<PermissionSurfaceDefinition>
         */
        public array $surfaces = [],
        /**
         * Every rule this permission declares OR is the target of — the same rule sits on both
         * ends, told apart by `$rule->permission === $this->enum`.
         *
         * Attached by {@see PermissionCollection}, the only place that sees every enum; empty on a
         * DTO built by {@see PermissionReflector} alone.
         *
         * @var list<PermissionRuleDto>
         */
        public array $rules = [],
    ) {}
}
