<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Happenv\LaravelAccessControl\Diagram\PermissionDiagrams diagram()
 * @method static void restrictUsing(\Closure(\Happenv\LaravelAccessControl\Contracts\PermissionDefinition): bool $restriction)
 * @method static bool isRestricted(\Happenv\LaravelAccessControl\Contracts\PermissionDefinition $permission)
 * @method static \Illuminate\Support\Collection<int, \Happenv\LaravelAccessControl\Contracts\PermissionDefinition> effectivePermissions(\Happenv\LaravelAccessControl\Contracts\AuthControllable $principal)
 *
 * @see \Happenv\LaravelAccessControl\AccessControl
 */
class AccessControl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Happenv\LaravelAccessControl\AccessControl::class;
    }
}
