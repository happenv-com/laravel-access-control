<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionGroupDto;
use Happenv\LaravelAccessControl\Dto\PermissionSubjectDto;
use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\CategoryPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\StoreSettingPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\SurfacedPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\TestSurface;

beforeEach(function (): void {
    $this->registry = new PermissionRegistry;
    $this->collection = new PermissionCollection($this->registry);
});

describe('PermissionCollection', function (): void {
    describe('getGroupedPermissions', function (): void {
        it('returns empty collection when no permissions registered', function (): void {
            $grouped = $this->collection->getGroupedPermissions();

            expect($grouped)->toBeEmpty();
        });

        it('returns permissions grouped by their group', function (): void {
            $this->registry->register([
                ProductPermission::class,
                CategoryPermission::class,
            ]);

            $grouped = $this->collection->getGroupedPermissions();

            expect($grouped)->toHaveCount(2);
            expect($grouped->keys()->toArray())->toContain('products');
            expect($grouped->keys()->toArray())->toContain('categories');
        });

        it('returns PermissionGroupDto instances', function (): void {
            $this->registry->register(ProductPermission::class);

            $grouped = $this->collection->getGroupedPermissions();

            expect($grouped->first())->toBeInstanceOf(PermissionGroupDto::class);
        });

        it('includes group metadata', function (): void {
            $this->registry->register(ProductPermission::class);

            $grouped = $this->collection->getGroupedPermissions();
            $productGroup = $grouped->get('products');

            expect($productGroup->name)->toBe('Products');
            expect($productGroup->slug)->toBe('products');
            expect($productGroup->description)->toBe('Permissions related to product management');
        });

        it('includes children permissions in group', function (): void {
            $this->registry->register(ProductPermission::class);

            $grouped = $this->collection->getGroupedPermissions();
            $productGroup = $grouped->get('products');

            expect($productGroup->children)->toHaveCount(4);
            expect($productGroup->children->first())->toBeInstanceOf(PermissionDto::class);
        });
    });

    describe('getPermissions', function (): void {
        it('returns empty collection when no permissions registered', function (): void {
            $permissions = $this->collection->getPermissions();

            expect($permissions)->toBeEmpty();
        });

        it('returns flat list of all permissions', function (): void {
            $this->registry->register([
                ProductPermission::class,
                CategoryPermission::class,
            ]);

            $permissions = $this->collection->getPermissions();

            expect($permissions)->toHaveCount(8); // 4 + 4
        });

        it('returns PermissionDto instances', function (): void {
            $this->registry->register(ProductPermission::class);

            $permissions = $this->collection->getPermissions();

            expect($permissions->first())->toBeInstanceOf(PermissionDto::class);
        });

        it('includes all permission slugs', function (): void {
            $this->registry->register([
                ProductPermission::class,
                CategoryPermission::class,
            ]);

            $permissions = $this->collection->getPermissions();
            $slugs = $permissions->pluck('slug')->toArray();

            expect($slugs)
                ->toContain('product.view')
                ->toContain('product.create')
                ->toContain('category.view')
                ->toContain('category.delete');
        });
    });
    describe('subjects', function (): void {
        it('splits a shared group into one subject per enum', function (): void {
            $this->registry->register([
                ProductPermission::class,
                StoreSettingPermission::class,
            ]);

            $productGroup = $this->collection->getGroupedPermissions()->get('products');

            expect($productGroup->subjects)->toHaveCount(2);
            expect($productGroup->subjects->first())->toBeInstanceOf(PermissionSubjectDto::class);
        });

        it('keeps the flat children list alongside the subjects', function (): void {
            $this->registry->register([
                ProductPermission::class,
                StoreSettingPermission::class,
            ]);

            $productGroup = $this->collection->getGroupedPermissions()->get('products');

            // 4 product + 2 store setting, none dropped by the split.
            expect($productGroup->children)->toHaveCount(6);
            expect($productGroup->subjects->sum(fn (PermissionSubjectDto $subject): int => $subject->children->count()))->toBe(6);
        });

        it('keys subjects by the declaring enum, not by the slug', function (): void {
            $this->registry->register([
                ProductPermission::class,
                StoreSettingPermission::class,
            ]);

            $productGroup = $this->collection->getGroupedPermissions()->get('products');

            expect($productGroup->subjects->keys()->toArray())
                ->toContain(ProductPermission::class)
                ->toContain(StoreSettingPermission::class);
        });

        it('leaves subjects empty when nothing is registered', function (): void {
            expect($this->collection->getSubjects())->toBeEmpty();
        });

        it('lists every subject across every group', function (): void {
            $this->registry->register([
                ProductPermission::class,
                CategoryPermission::class,
                StoreSettingPermission::class,
            ]);

            expect($this->collection->getSubjects()->keys()->toArray())
                ->toContain(ProductPermission::class)
                ->toContain(CategoryPermission::class)
                ->toContain(StoreSettingPermission::class);
        });
    });

    describe('availableOn', function (): void {
        it('returns only the slugs declared for the surface', function (): void {
            $this->registry->register(SurfacedPermission::class);

            expect($this->collection->availableOn(TestSurface::Machine)->all())
                ->toBe(['surfaced.view', 'surfaced.update']);
        });

        it('returns only what the surface declares, not the whole catalogue', function (): void {
            $this->registry->register(SurfacedPermission::class);

            expect($this->collection->availableOn(TestSurface::Panel)->all())
                ->toBe(['surfaced.update']);
        });
    });
});
