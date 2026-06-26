<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\AccessControl;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Voters\ProductVoter;
use Happenv\LaravelAccessControl\VoterRegistry;

beforeEach(function (): void {
    $this->voterRegistry = new VoterRegistry;
    $this->permissionRegistry = new PermissionRegistry;
    $this->accessControl = new AccessControl($this->voterRegistry, $this->permissionRegistry);
});

describe('AccessControl', function (): void {
    it('can register a voter class', function (): void {
        $this->accessControl->registerVoter(ProductVoter::class);

        expect($this->voterRegistry->countVoters(ProductPermission::Delete))->toBe(1);
    });

    it('can register a permission', function (): void {
        $this->accessControl->registerPermission(ProductPermission::class);

        expect($this->permissionRegistry->isDefined(ProductPermission::class))->toBeTrue();
    });

    it('can reflect permission', function (): void {
        $this->accessControl->reflectPermission(ProductPermission::class);
        // This method is currently a no-op, just ensure it doesn't throw
        expect(true)->toBeTrue();
    });
});
