<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A condition an ACCOUNT must meet for a permission to be in effect — implemented by an attribute
 * placed on a permission enum (it then guards every case) or on one case. An application writes its
 * own; nothing is registered, the attribute is found by this interface.
 *
 * Checked last — after runtime restrictions and the rules between permissions — and only for a
 * principal that is `Authenticatable`: a role stores grants, it does not sign in.
 *
 * `check()` MUST be free of side effects and idempotent within a request, and SHOULD avoid I/O. It
 * runs on every check, twice in one gate check (once in the trait, once in the gate), and its answer
 * is never cached: a cache would outlive a change in the account, such as MFA switched on mid-request.
 */
interface PermissionCondition
{
    /**
     * True when the account meets the condition for this permission.
     *
     * The permission is handed over on purpose: one attribute class may guard many permissions, and
     * a condition may depend on which one it guards — reading the case's other attributes, say.
     */
    public function check(PermissionDefinition $permission, Authenticatable $principal): bool;
}
