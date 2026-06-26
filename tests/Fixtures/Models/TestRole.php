<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Traits\HasRoles;
use Illuminate\Support\Collection;

class Role implements AuthControllable
{
    use HasRoles;

    public function __construct(
        private Collection $roles = new Collection,
    ) {}

    public function getRoles(): iterable
    {
        return $this->roles;
    }

    public function hasPermissionTo($permission): bool
    {
        return false;
    }
}
