<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

/**
 * A principal or role that says how it is named where grants are drawn — a role's name, a user's
 * display name. Without it a diagram falls back to an Eloquent `name` attribute, then the class.
 */
interface DescribesGrantHolder
{
    public function getGrantHolderName(): string;
}
