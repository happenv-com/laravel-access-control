<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Illuminate\Support\Collection;

/**
 * A role kept in memory that CANNOT hand over its grants: asked `hasPermissionTo()`, it answers with
 * its own rules applied — the fallback for roles written before `HoldsGrants` existed.
 */
class InMemoryRole implements AuthControllable
{
    use HasPermissions;

    /**
     * @param  list<string>  $grants
     */
    public function __construct(
        private array $grants = [],
    ) {}

    protected function getPermissions(): Collection
    {
        return new Collection($this->grants);
    }

    protected function setPermissions(Collection $permissions): void
    {
        $this->grants = $permissions->values()->all();
    }
}
