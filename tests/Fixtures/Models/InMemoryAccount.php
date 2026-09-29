<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Concerns\AuthenticatesInMemory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account holding its grants directly — a machine user, an API key.
 */
class InMemoryAccount extends InMemoryRole implements Authenticatable
{
    use AuthenticatesInMemory;
}
