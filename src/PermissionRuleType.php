<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\Requires;

/**
 * The three rules a permission can declare about another — {@see Requires}, {@see ImpliedBy} and
 * {@see ConflictsWith}.
 *
 * Backed, so a permission-management UI can hand it to a front end as it is.
 */
enum PermissionRuleType: string
{
    case Requires = 'requires';
    case ImpliedBy = 'implied-by';
    case ConflictsWith = 'conflicts-with';
}
