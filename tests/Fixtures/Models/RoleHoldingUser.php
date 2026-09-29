<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

/**
 * The user fixture with roles a test hands it — `HasRolesAndPermissions` with both sources.
 */
class RoleHoldingUser extends User
{
    /**
     * @var list<object>
     */
    public array $heldRoles = [];

    public function getRoles(): iterable
    {
        return $this->heldRoles;
    }
}
