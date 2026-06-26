<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Happenv\LaravelAccessControl\AccessControl
 */
class AccessControl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Happenv\LaravelAccessControl\AccessControl::class;
    }
}
