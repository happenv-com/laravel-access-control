<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Conditions;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use WeakMap;

/**
 * Which principal meets which {@see RequiresFlag} — held per object, so every test builds the
 * accounts it needs and nothing outlives them.
 */
final class Flags
{
    /** How many times a condition was checked — to prove an answer is never remembered. */
    public static int $checks = 0;

    /** The permission the last check was asked about. */
    public static ?PermissionDefinition $checked = null;

    /** @var WeakMap<object, array<string, true>>|null */
    private static ?WeakMap $raised = null;

    public static function raise(object $principal, string $flag = 'mfa'): void
    {
        self::$raised ??= new WeakMap;
        self::$raised[$principal] = [...(self::$raised[$principal] ?? []), $flag => true];
    }

    public static function lower(object $principal, string $flag = 'mfa'): void
    {
        if (self::$raised === null || ! isset(self::$raised[$principal])) {
            return;
        }

        $flags = self::$raised[$principal];
        unset($flags[$flag]);
        self::$raised[$principal] = $flags;
    }

    public static function raised(object $principal, string $flag): bool
    {
        return self::$raised !== null && isset(self::$raised[$principal][$flag]);
    }

    public static function reset(): void
    {
        self::$raised = null;
        self::$checks = 0;
        self::$checked = null;
    }
}
