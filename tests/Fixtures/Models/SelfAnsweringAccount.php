<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Concerns\AuthenticatesInMemory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account answering `hasPermissionTo()` itself — an administrator short-circuit that never
 * reaches the library's traits.
 */
class SelfAnsweringAccount implements AuthControllable, Authenticatable
{
    use AuthenticatesInMemory;

    public function hasPermissionTo(PermissionDefinition $permission): bool
    {
        return true;
    }
}
