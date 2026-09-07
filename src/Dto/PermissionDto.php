<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;

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
    ) {}
}
