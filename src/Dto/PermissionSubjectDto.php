<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Support\Collection;

/**
 * The middle level between a group and its permissions: one subject per
 * permission enum.
 *
 * A group answers "which module owns this?", a subject answers "what is this
 * permission about?" — the enum is already a 1:1 stand-in for the thing being
 * guarded (`UserPermission` guards users), so the level costs no new
 * declarations. Without it, a group that several enums share renders as one
 * flat list in which `View` appears once per enum with nothing to tell the
 * entries apart.
 */
final class PermissionSubjectDto
{
    public function __construct(
        public string $name,
        public string $slug,
        /**
         * @var class-string<PermissionDefinition>
         */
        public string $enum,
        /**
         * @var Collection<int, PermissionDto>
         */
        public Collection $children,
        public ?string $description = null,
    ) {}
}
