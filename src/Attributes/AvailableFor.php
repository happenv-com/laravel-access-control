<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;

/**
 * The surfaces a permission is available on.
 *
 * On a CASE it states that case's surfaces. On the enum CLASS it states the default for every case
 * that says nothing — and a case that speaks REPLACES that default rather than adding to it, which
 * is the only rule reflection can check without a precedence table.
 *
 * There is deliberately no `UnavailableFor`. A surface that grants nothing by default has nothing
 * to subtract from, and a whitelist beside a blacklist is a space of contradictions
 * (`AvailableFor(X)` next to `UnavailableFor(X)`) that would need a precedence rule, a guard for it
 * and a document nobody reads.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class AvailableFor
{
    /** @var list<PermissionSurfaceDefinition> */
    public array $surfaces;

    public function __construct(PermissionSurfaceDefinition ...$surfaces)
    {
        // Written out because a variadic cannot be promoted, and assigned AS IS because an
        // attribute can only ever pass these positionally — PHP collects such arguments into a
        // 0-indexed list already. `array_values()` stood here and normalised nothing; a reader had
        // to work out that it was guarding against a shape this constructor cannot receive.
        $this->surfaces = $surfaces;
    }
}
