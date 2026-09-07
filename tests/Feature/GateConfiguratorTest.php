<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\GateConfigurator;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Product;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Voters\ProductVoter;
use Happenv\LaravelAccessControl\VoterRegistry;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->permissionRegistry = resolve(PermissionRegistry::class);
    $this->voterRegistry = resolve(VoterRegistry::class);
    $this->gateConfigurator = resolve(GateConfigurator::class);

    $this->permissionRegistry->register(ProductPermission::class);
    $this->voterRegistry->registerClass(ProductVoter::class);
    $this->gateConfigurator->configure();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'permissions' => [
            'product.view',
            'product.create',
            'product.update',
            'product.delete',
        ],
    ]);
});

describe('GateConfigurator', function (): void {
    describe('authorization without voters', function (): void {
        it('allows user with permission', function (): void {
            $this->actingAs($this->user);

            expect(Gate::allows(ProductPermission::View))->toBeTrue();
            expect(Gate::allows(ProductPermission::Create))->toBeTrue();
        });

        it('denies user without permission', function (): void {
            $userWithoutPermissions = User::create([
                'name' => 'No Permissions User',
                'email' => 'noperm@example.com',
                'password' => 'password',
                'permissions' => [],
            ]);

            $this->actingAs($userWithoutPermissions);

            expect(Gate::allows(ProductPermission::View))->toBeFalse();
            expect(Gate::allows(ProductPermission::Create))->toBeFalse();
        });

        it('denies unauthenticated user', function (): void {
            expect(Gate::allows(ProductPermission::View))->toBeFalse();
        });

        it('says which of the two refusals it is', function (): void {
            // The two denials this gate can produce are DIFFERENT PROBLEMS on the caller's side and
            // take different fixes: no principal at all versus a principal that lacks the ability.
            // Consumers publish these messages verbatim -- Sellero's public GraphQL API puts them in
            // its error envelope -- so the strings are part of this package's contract, not debug
            // text, and they are asserted here as VALUES rather than as "some denial".
            $withoutPermissions = User::create([
                'name' => 'No Permissions User',
                'email' => 'noperm@example.com',
                'password' => 'password',
                'permissions' => [],
            ]);

            // NO USER AT ALL. The gate closure takes `?Authenticatable $user = null`, so Laravel runs
            // it for guests instead of short-circuiting, and this branch is the one a caller sees.
            expect(Gate::inspect(ProductPermission::View)->message())->toBe('Unauthenticated.');

            // A USER, WITHOUT THE ABILITY -- the other message, which must not drift into the first.
            $this->actingAs($withoutPermissions);

            expect(Gate::inspect(ProductPermission::View)->message())->toBe('Unauthorized.');
        });
    });

    describe('authorization with voters', function (): void {
        it('allows when user has permission and voter allows', function (): void {
            $this->actingAs($this->user);

            $product = Product::create(['name' => 'Unlocked Product', 'is_locked' => false]);

            expect(Gate::allows(ProductPermission::Delete, $product))->toBeTrue();
            expect(Gate::allows(ProductPermission::Update, $product))->toBeTrue();
        });

        it('denies when voter denies even if user has permission', function (): void {
            $this->actingAs($this->user);

            $lockedProduct = Product::create(['name' => 'Locked Product', 'is_locked' => true]);

            expect(Gate::allows(ProductPermission::Delete, $lockedProduct))->toBeFalse();
            expect(Gate::allows(ProductPermission::Update, $lockedProduct))->toBeFalse();
        });

        it('denies when user lacks permission even if voter would allow', function (): void {
            $userWithoutDelete = User::create([
                'name' => 'Limited User',
                'email' => 'limited@example.com',
                'password' => 'password',
                'permissions' => ['product.view'],
            ]);

            $this->actingAs($userWithoutDelete);

            $product = Product::create(['name' => 'Unlocked Product', 'is_locked' => false]);

            expect(Gate::allows(ProductPermission::Delete, $product))->toBeFalse();
        });
    });

    describe('using User model methods', function (): void {
        it('works with can method', function (): void {
            $product = Product::create(['name' => 'Unlocked Product', 'is_locked' => false]);

            expect($this->user->can(ProductPermission::View))->toBeTrue();
            expect($this->user->can(ProductPermission::Delete, $product))->toBeTrue();
        });

        it('works with cannot method', function (): void {
            $lockedProduct = Product::create(['name' => 'Locked Product', 'is_locked' => true]);

            expect($this->user->cannot(ProductPermission::Delete, $lockedProduct))->toBeTrue();
        });
    });
});

describe('display_permission_in_exception', function (): void {
    // `beforeEach` above already registered ProductPermission and called configure() once
    // against the DEFAULT config value -- registering it again here would collide with that
    // (PermissionRegistry::register() throws on a duplicate enum). Not re-calling configure()
    // after config()->set() is deliberate too: it proves the message is read at REFUSAL time,
    // not frozen into the closure when configure() ran.
    it('refuses without naming the permission by default', function (): void {
        config()->set('access-control.display_permission_in_exception', false);

        $user = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);

        expect(Gate::forUser($user)->inspect(ProductPermission::View)->message())->toBe('Unauthorized.');
    });

    it('names the permission when the option is on', function (): void {
        config()->set('access-control.display_permission_in_exception', true);

        $user = User::create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'x']);

        expect(Gate::forUser($user)->inspect(ProductPermission::View)->message())
            ->toBe('Unauthorized for product.view');
    });
});
