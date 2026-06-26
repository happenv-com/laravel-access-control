<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

final class PermissionDto
{
    public function __construct(
        public string $name,
        public PermissionDefinition $enum,
        public string $slug,
        public ?string $description = null,
    ) {}
}
