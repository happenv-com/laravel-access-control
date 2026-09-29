<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Conditions;

use Attribute;
use Happenv\LaravelAccessControl\Contracts\DescribesPermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Met by an account {@see Flags} raised the flag for — a stand-in for "has MFA", "verified e-mail".
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class RequiresFlag implements DescribesPermissionCondition, PermissionCondition
{
    public function __construct(
        public string $flag = 'mfa',
    ) {}

    public function check(PermissionDefinition $permission, Authenticatable $principal): bool
    {
        Flags::$checks++;
        Flags::$checked = $permission;

        return Flags::raised($principal, $this->flag);
    }

    public function describe(): string
    {
        return 'Requires ' . $this->flag;
    }
}
