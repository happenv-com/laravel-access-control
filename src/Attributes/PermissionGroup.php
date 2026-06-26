<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Happenv\LaravelAccessControl\Contracts\PermissionGroupDefinition;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PermissionGroup
{
    public function __construct(
        /**
         * @var class-string<PermissionGroupDefinition> $group
         */
        public string $group,
    ) {}
}
