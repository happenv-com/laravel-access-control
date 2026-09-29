<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

enum EdgeKind: string
{
    /**
     * The principal holds a role, or its direct grants.
     */
    case Holds = 'holds';

    /**
     * The holder stores the permission — raw, as `HoldsGrants` or `HasPermissions` hands it over.
     */
    case Stores = 'stores';

    /**
     * A role that cannot hand over its grants said it may act on the permission — with its own
     * rules and any restriction already applied.
     */
    case Grants = 'grants';

    case Requires = 'requires';

    /**
     * From the implying permission to the implied one.
     */
    case Implies = 'implies';

    /**
     * From the permission that declares the conflict — the one that loses it — to the other.
     */
    case ConflictsWith = 'conflicts-with';

    /**
     * Whether the edge says who holds what, rather than how permissions relate.
     */
    public function isStructural(): bool
    {
        return match ($this) {
            self::Holds, self::Stores, self::Grants => true,
            default => false,
        };
    }
}
