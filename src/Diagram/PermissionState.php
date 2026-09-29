<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

/**
 * Why a permission in a principal's diagram is, or is not, effective.
 */
enum PermissionState: string
{
    /**
     * Effective, and stored.
     */
    case Allowed = 'allowed';

    /**
     * Effective without being stored: something the principal holds implies it.
     */
    case Implied = 'implied';

    /**
     * The rules allow it; a runtime restriction withholds it.
     */
    case Restricted = 'restricted';

    /**
     * Granted, but a permission it requires is not active.
     */
    case MissingRequirement = 'missing-requirement';

    /**
     * Granted and active, but it loses a conflict it declares.
     */
    case Conflict = 'conflict';

    /**
     * The principal's own `hasPermissionTo()` refuses it for a reason the rules do not explain.
     */
    case Denied = 'denied';

    /**
     * Neither stored nor implied — drawn only because a drawn permission's rule points at it.
     */
    case NotGranted = 'not-granted';
}
