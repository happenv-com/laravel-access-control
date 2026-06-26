<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

interface PermissionGroupDefinition
{
    public function getName(): string;

    public function getDescription(): ?string;

    public function getSlug(): string;
}
