<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Happenv\LaravelAccessControl\Traits\HasRoles;

/**
 * A grant container — typically a role — that hands its RAW grants to the principal holding it.
 *
 * A principal resolves the rules between permissions once, over the union of all its grants: a
 * requirement one role stores then satisfies a permission another role stores, and a conflict
 * between two roles is seen. A role that is not this can only answer `hasPermissionTo()`, with its
 * own rules applied, and a requirement stored in another role does not count for it.
 *
 * {@see HasPermissions} provides the method; a role using it only has to declare the interface.
 * {@see HasRoles} reads it once per principal instance.
 */
interface HoldsGrants
{
    /**
     * The permission values stored on this holder, raw: no rules, no restrictions.
     *
     * @return iterable<string>
     */
    public function getGrants(): iterable;
}
