<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

/**
 * A condition that names itself for a UI or a diagram. Optional: a condition without it is shown by
 * its class name.
 */
interface DescribesPermissionCondition
{
    /**
     * A short label in the current locale — "Requires MFA".
     */
    public function describe(): string;
}
