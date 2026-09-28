<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Closure;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

/**
 * Permissions the application withholds from EVERY principal while some condition holds — a
 * read-only mode, a maintenance window, a suspended organisation.
 *
 * A restriction is not a revocation: nothing is taken from anybody's grants, and when the condition
 * ends everybody holds exactly what they held before. It sits in front of the grant, so the gate and
 * the {@see Traits\HasPermissions} / {@see Traits\HasRoles} checks all answer `false` for a
 * restricted permission, whoever asks and whatever they were granted.
 *
 * The package owns the QUESTION and never the condition. Which permissions a condition withholds
 * is the application's call, usually by what the permission declares
 * (`PermissionReflector::mutatesData()`), and so is where the condition's state lives.
 *
 * SAFE UNDER A LONG-RUNNING SERVER because nothing here is an answer. The instance is bound once per
 * application and holds only the closures, registered at boot the way gate abilities are; every
 * `isRestricted()` calls them again. A result cached here would outlive the request it was computed
 * for — refusing after the condition ended or allowing after it began. The closures must not cache
 * across requests either: read the condition from something request-scoped or from its source.
 */
final class PermissionRestrictions
{
    /**
     * @var list<Closure(PermissionDefinition): bool>
     */
    private array $restrictions = [];

    /**
     * Add a restriction. The closure returns `true` to withhold the permission it is given.
     *
     * Restrictions only ever ADD: a permission is restricted when ANY closure says so, so one
     * condition can never lift what another withholds.
     *
     * @param  Closure(PermissionDefinition): bool  $restriction
     */
    public function restrictUsing(Closure $restriction): void
    {
        $this->restrictions[] = $restriction;
    }

    public function isRestricted(PermissionDefinition $permission): bool
    {
        foreach ($this->restrictions as $restriction) {
            if ($restriction($permission) === true) {
                return true;
            }
        }

        return false;
    }
}
