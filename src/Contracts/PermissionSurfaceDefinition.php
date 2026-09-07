<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Contracts;

use BackedEnum;

/**
 * A surface on which a permission means something — a panel, a machine API, an agent.
 *
 * The package owns the CONCEPT and never the list: which surfaces exist is a fact about the
 * application, and an enum shipped here would be a list every application had to bend to.
 * Implemented by a userland backed enum, exactly as {@see PermissionDefinition} is.
 *
 * NOTHING HERE DEFINES A DEFAULT. "What does a permission that declares no surface mean?" is
 * answerable only by the surface asking — a panel that grants everything and a machine API that
 * grants nothing are both correct, and they disagree.
 */
interface PermissionSurfaceDefinition extends BackedEnum {}
