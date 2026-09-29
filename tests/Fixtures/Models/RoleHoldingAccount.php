<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Concerns\AuthenticatesInMemory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account holding roles and nothing directly — `HasRoles` on somebody who signs in.
 */
class RoleHoldingAccount extends RoleHolder implements Authenticatable
{
    use AuthenticatesInMemory;
}
