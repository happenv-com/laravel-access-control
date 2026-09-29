<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

/**
 * What a diagram draws: the rules of the whole catalogue, or what one principal may do and why.
 */
enum DiagramKind: string
{
    case Catalogue = 'catalogue';
    case Principal = 'principal';
}
