<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;

#[Attribute]
final readonly class PermissionName
{
    public function __construct(
        public string $value,
    ) {}
}
