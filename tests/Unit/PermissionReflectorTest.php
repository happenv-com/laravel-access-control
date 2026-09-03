<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Exceptions\PermissionGroupRequiredException;
use Happenv\LaravelAccessControl\PermissionReflector;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Groups\ProductGroup;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\PermissionWithoutGroup;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\StoreSettingPermission;

describe('PermissionReflector', function (): void {
    describe('getGroup', function (): void {
        it('returns permission group instance', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $group = $reflector->getGroup();

            expect($group)->toBeInstanceOf(ProductGroup::class);
            expect($group->getName())->toBe('Products');
            expect($group->getSlug())->toBe('products');
            expect($group->getDescription())->toBe('Permissions related to product management');
        });

        it('throws exception when group attribute is missing', function (): void {
            $reflector = new PermissionReflector(PermissionWithoutGroup::class);

            expect(fn () => $reflector->getGroup())
                ->toThrow(PermissionGroupRequiredException::class);
        });
    });

    describe('getValues', function (): void {
        it('returns collection of permission DTOs', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();

            expect($values)->toHaveCount(4);
        });

        it('includes custom name from attribute', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();
            $viewPermission = $values->firstWhere('slug', 'product.view');

            expect($viewPermission->name)->toBe('View Products');
        });

        it('includes custom description from attribute', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();
            $viewPermission = $values->firstWhere('slug', 'product.view');

            expect($viewPermission->description)->toBe('Allows viewing product details');
        });

        it('generates default name from the enum case and its subject', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();
            $deletePermission = $values->firstWhere('slug', 'product.delete');

            // Qualified by the SUBJECT, not the group: a group shared by several
            // enums would render every one of their `Delete` cases identically.
            expect($deletePermission->name)->toBe('Delete Product');
        });

        it('returns null description when attribute is missing', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();
            $deletePermission = $values->firstWhere('slug', 'product.delete');

            expect($deletePermission->description)->toBeNull();
        });

        it('includes enum instance in DTO', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();
            $viewPermission = $values->firstWhere('slug', 'product.view');

            expect($viewPermission->enum)->toBe(ProductPermission::View);
        });

        it('includes slug in DTO', function (): void {
            $reflector = new PermissionReflector(ProductPermission::class);

            $values = $reflector->getValues();

            expect($values->pluck('slug')->toArray())
                ->toContain('product.view')
                ->toContain('product.create')
                ->toContain('product.update')
                ->toContain('product.delete');
        });
    });
    describe('getSubject', function (): void {
        it('names the subject after the enum, without the Permission suffix', function (): void {
            $subject = (new PermissionReflector(ProductPermission::class))->getSubject();

            expect($subject->name)->toBe('Product');
            expect($subject->slug)->toBe('product');
            expect($subject->enum)->toBe(ProductPermission::class);
            expect($subject->description)->toBeNull();
        });

        it('prefers a class level name and description over the derived ones', function (): void {
            $subject = (new PermissionReflector(StoreSettingPermission::class))->getSubject();

            expect($subject->name)->toBe('Store Settings');
            expect($subject->description)->toBe('Settings that apply to the whole store');
        });

        it('keeps the derived slug when the name is overridden', function (): void {
            $subject = (new PermissionReflector(StoreSettingPermission::class))->getSubject();

            // The slug addresses the subject in URLs and DOM keys, so it must not
            // move when someone retitles or translates the label.
            expect($subject->slug)->toBe('store-setting');
        });

        it('carries the enum cases as children', function (): void {
            $subject = (new PermissionReflector(ProductPermission::class))->getSubject();

            expect($subject->children)->toHaveCount(4);
        });

        it('qualifies a default permission name with the overridden subject name', function (): void {
            $values = (new PermissionReflector(StoreSettingPermission::class))->getValues();

            expect($values->firstWhere('slug', 'store-setting.view')->name)->toBe('View Store Settings');
        });
    });

});
