<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements AuthControllable
{
    use HasFactory;
    use HasRolesAndPermissions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    protected function getPermissions(): Collection
    {
        return new Collection($this->permissions ?? []);
    }

    protected function setPermissions(Collection $permissions): void
    {
        $this->permissions = $permissions->toArray();
        $this->save();
    }

    public function getRoles(): iterable
    {
        return [];
    }
}
