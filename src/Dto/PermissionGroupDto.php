<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Dto;

use Illuminate\Support\Collection;

final class PermissionGroupDto
{
    public function __construct(
        public string $name,
        public string $slug,
        /**
         * Every permission in the group, flattened. Kept as the group's primary
         * child list so existing consumers keep working; `$subjects` is the same
         * set, one level deeper.
         *
         * @var Collection<int, PermissionDto>
         */
        public Collection $children,
        public ?string $description = null,
        /**
         * The group's permissions grouped by the enum that declares them, keyed
         * by subject slug.
         *
         * @var Collection<string, PermissionSubjectDto>
         */
        public Collection $subjects = new Collection,
    ) {}
}
