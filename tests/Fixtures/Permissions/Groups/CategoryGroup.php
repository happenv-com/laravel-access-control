<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups;

use Happenv\LaravelAccessControl\Contracts\PermissionGroupDefinition;

final class CategoryGroup implements PermissionGroupDefinition
{
    public function getName(): string
    {
        return 'Categories';
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getSlug(): string
    {
        return 'categories';
    }
}
