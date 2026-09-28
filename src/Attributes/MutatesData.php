<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Attributes;

use Attribute;
use Happenv\LaravelAccessControl\PermissionReflector;
use Happenv\LaravelAccessControl\PermissionRestrictions;

/**
 * Whether exercising a permission WRITES data.
 *
 * On a CASE it answers for that case. On the enum CLASS it states the default for every case that
 * says nothing — and a case that speaks REPLACES that default, exactly as {@see AvailableFor} does:
 * a class marked `#[MutatesData]` with `#[MutatesData(false)]` on its `View` case reads as "all of
 * these write, except viewing". `false` is an answer, not an absence, so it never falls through.
 *
 * A permission that declares nothing reads as NOT mutating. The package cannot know better, and a
 * default of `true` would make every permission of an application that never heard of this
 * attribute look like a write. An application that needs every permission classified asks
 * {@see PermissionReflector::declaresMutatesData()} and fails its own build on the answer.
 *
 * The package only READS this. What a write-capable permission means at runtime — refused in a
 * read-only mode, audited, rate-limited — belongs to the application, which says so through
 * {@see PermissionRestrictions}.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class MutatesData
{
    public function __construct(
        public bool $mutates = true,
    ) {}
}
