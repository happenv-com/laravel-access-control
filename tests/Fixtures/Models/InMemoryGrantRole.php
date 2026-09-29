<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\HoldsGrants;

/**
 * The same role, handing its raw grants to the principal holding it — `getGrants()` comes with
 * `HasPermissions`; declaring the interface is all a role has to do.
 */
class InMemoryGrantRole extends InMemoryRole implements HoldsGrants {}
