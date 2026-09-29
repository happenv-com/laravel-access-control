<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

enum NodeKind: string
{
    case Principal = 'principal';
    case Role = 'role';

    /**
     * The principal's own grants, held without a role.
     */
    case Direct = 'direct';

    case Permission = 'permission';
}
