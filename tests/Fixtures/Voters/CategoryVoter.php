<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Voters;

use Happenv\LaravelAccessControl\Attributes\VoterForPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Category;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\CategoryPermission;
use Illuminate\Auth\Access\Response;

final class CategoryVoter
{
    #[VoterForPermission(CategoryPermission::Delete)]
    public function preventDeletingCategoryWithProducts(User $user, Category | string | null $category = null): Response
    {
        if (! $category instanceof Category) {
            return Response::allow();
        }

        if ($category->products()->exists()) {
            return Response::deny('Cannot delete category with assigned products.');
        }

        return Response::allow();
    }
}
