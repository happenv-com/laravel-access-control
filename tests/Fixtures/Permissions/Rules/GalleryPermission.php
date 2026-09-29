<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules;

use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;

/**
 * A module that knows the Product module and declares its rules against it — the direction the
 * package is built for: the Product module never learns that this enum exists.
 */
#[PermissionGroup(ProductGroup::class)]
enum GalleryPermission: string implements PermissionDefinition
{
    #[Requires(ProductPermission::View, reason: 'rules.needs')]
    case View = 'gallery.view';

    #[ImpliedBy(ProductPermission::Update, reason: ':permission comes with :other')]
    case Manage = 'gallery.manage';

    #[ConflictsWith(ProductPermission::Delete)]
    case Archive = 'gallery.archive';
}
