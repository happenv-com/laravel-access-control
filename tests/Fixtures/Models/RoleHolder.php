<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Traits\HasRoles;

/**
 * A principal holding roles and nothing else.
 */
class RoleHolder implements AuthControllable
{
    use HasRoles;

    /**
     * @param  list<AuthControllable>  $roles
     */
    public function __construct(
        private array $roles = [],
    ) {}

    public function getRoles(): iterable
    {
        return $this->roles;
    }
}
