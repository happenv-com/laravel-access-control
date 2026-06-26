<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

#[Attribute(Attribute::TARGET_METHOD)]
final class VoterForPermission
{
    public function __construct(
        public PermissionDefinition $permission,
    ) {}
}
