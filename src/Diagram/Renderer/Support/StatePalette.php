<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram\Renderer\Support;

use Happenv\LaravelAccessControl\Diagram\PermissionState;

/**
 * One set of colours for every renderer that draws states: green for what works, blue for what
 * comes with something else, amber for what is withheld, red for a conflict or a refusal, grey for
 * what is missing — and a dashed outline for what is not there at all.
 *
 * @internal
 */
final class StatePalette
{
    public static function fill(PermissionState $state): string
    {
        return match ($state) {
            PermissionState::Allowed => '#dcfce7',
            PermissionState::Implied => '#dbeafe',
            PermissionState::Restricted => '#fef3c7',
            PermissionState::MissingRequirement => '#f3f4f6',
            PermissionState::Conflict, PermissionState::Denied => '#fee2e2',
            PermissionState::NotGranted => '#ffffff',
        };
    }

    public static function stroke(PermissionState $state): string
    {
        return match ($state) {
            PermissionState::Allowed => '#16a34a',
            PermissionState::Implied => '#2563eb',
            PermissionState::Restricted => '#d97706',
            PermissionState::MissingRequirement => '#6b7280',
            PermissionState::Conflict => '#dc2626',
            PermissionState::Denied => '#991b1b',
            PermissionState::NotGranted => '#9ca3af',
        };
    }

    public static function dashed(PermissionState $state): bool
    {
        return $state === PermissionState::MissingRequirement || $state === PermissionState::NotGranted;
    }
}
