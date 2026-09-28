<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionRestrictions;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;

describe('PermissionRestrictions', function (): void {
    it('restricts nothing until a restriction is registered', function (): void {
        expect((new PermissionRestrictions)->isRestricted(ProductPermission::Delete))->toBeFalse();
    });

    it('restricts what a closure says it restricts, and only that', function (): void {
        $restrictions = new PermissionRestrictions;

        $restrictions->restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Delete);

        expect($restrictions->isRestricted(ProductPermission::Delete))->toBeTrue()
            ->and($restrictions->isRestricted(ProductPermission::View))->toBeFalse();
    });

    it('restricts a permission ANY closure restricts', function (): void {
        $restrictions = new PermissionRestrictions;

        $restrictions->restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Delete);
        $restrictions->restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Update);

        expect($restrictions->isRestricted(ProductPermission::Delete))->toBeTrue()
            ->and($restrictions->isRestricted(ProductPermission::Update))->toBeTrue()
            ->and($restrictions->isRestricted(ProductPermission::View))->toBeFalse();
    });

    it('asks its closures again on every check', function (): void {
        // A restriction follows a condition that can start and end while the process lives -- a
        // long-running worker serves many requests between two boots. Answering from anything but a
        // fresh call would keep refusing after the condition ended, or keep allowing after it began.
        $restrictions = new PermissionRestrictions;
        $readOnly = false;

        $restrictions->restrictUsing(function (PermissionDefinition $permission) use (&$readOnly): bool {
            return $readOnly;
        });

        expect($restrictions->isRestricted(ProductPermission::Update))->toBeFalse();

        $readOnly = true;

        expect($restrictions->isRestricted(ProductPermission::Update))->toBeTrue();

        $readOnly = false;

        expect($restrictions->isRestricted(ProductPermission::Update))->toBeFalse();
    });

    it('is one instance per application', function (): void {
        expect(resolve(PermissionRestrictions::class))->toBe(resolve(PermissionRestrictions::class));
    });

    it('is reached through the facade', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Delete);

        expect(resolve(PermissionRestrictions::class)->isRestricted(ProductPermission::Delete))->toBeTrue()
            ->and(AccessControl::isRestricted(ProductPermission::Delete))->toBeTrue()
            ->and(AccessControl::isRestricted(ProductPermission::View))->toBeFalse();
    });
});
