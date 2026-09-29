<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

/**
 * What is wrong with a declaration — see {@see PermissionGraph::problemDetails()}.
 */
enum PermissionProblemType: string
{
    /** It requires, directly or through a requirement, a permission it conflicts with. */
    case RequiresConflicting = 'requires-conflicting';

    /** It implies, directly or through an implication, a permission it conflicts with that needs nothing else. */
    case ImpliesConflicting = 'implies-conflicting';

    /** A rule of it points at a permission whose enum is not registered. */
    case UnregisteredTarget = 'unregistered-target';
}
