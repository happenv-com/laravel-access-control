<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\GateConfigurator;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\Product;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Voters\ProductVoter;
use Happenv\LaravelAccessControl\VoterRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
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

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']));

        expect(Gate::inspect(ProductPermission::View)->message())->toBe('Unauthorized.');
    });

    it('names the permission when the option is on', function (): void {
        config()->set('access-control.display_permission_in_exception', true);

        $this->actingAs(User::create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'x']));

        expect(Gate::inspect(ProductPermission::View)->message())
            ->toBe('Unauthorized for product.view');
    });
});

describe('runtime restrictions', function (): void {
    it('refuses a restricted permission in the words it refuses a missing one', function (bool $display, string $message): void {
        // The refusal text is a contract consumers publish verbatim and match on. A restriction is,
        // on the caller's side, the same problem as a missing grant -- this principal cannot do this
        // now -- so it must not introduce a third message for every consumer to learn.
        config()->set('access-control.display_permission_in_exception', $display);

        $withoutPermissions = User::create(['name' => 'C', 'email' => 'c@example.com', 'password' => 'x', 'permissions' => []]);

        // Taken BEFORE the restriction exists: afterwards the gate refuses this user on the
        // restriction too, and the comparison below would hold two restricted refusals side by side.
        $missing = Gate::forUser($withoutPermissions)->inspect(ProductPermission::Delete);

        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Delete);

        $restricted = Gate::forUser($this->user)->inspect(ProductPermission::Delete);

        expect($restricted->denied())->toBeTrue()
            ->and($restricted->message())->toBe($message)
            ->and($restricted->message())->toBe($missing->message());
    })->with([
        'without naming the permission' => [false, 'Unauthorized.'],
        'naming the permission' => [true, 'Unauthorized for product.delete'],
    ]);

    it('still allows what is not restricted', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === ProductPermission::Delete);

        $this->actingAs($this->user);

        expect(Gate::allows(ProductPermission::View))->toBeTrue()
            ->and(Gate::allows(ProductPermission::Delete))->toBeFalse();
    });

    it('still tells a guest it is unauthenticated', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => true);

        expect(Gate::inspect(ProductPermission::View)->message())->toBe('Unauthenticated.');
    });

    it('refuses even a principal whose own check ignores restrictions', function (): void {
        // An application may answer `hasPermissionTo()` itself -- an administrator short-circuit is
        // the usual case -- and never reach the package's traits. The gate must hold the line alone.
        $administrator = new class extends Authenticatable implements AuthControllable
        {
            public function hasPermissionTo(PermissionDefinition $permission): bool
            {
                return true;
            }
        };

        $readOnly = false;

        AccessControl::restrictUsing(function (PermissionDefinition $permission) use (&$readOnly): bool {
            return $readOnly;
        });

        expect(Gate::forUser($administrator)->allows(ProductPermission::View))->toBeTrue();

        $readOnly = true;

        expect(Gate::forUser($administrator)->inspect(ProductPermission::View)->message())->toBe('Unauthorized.');

        // Configured once, asked every time: the same gate lets it through again once the condition ends.
        $readOnly = false;

        expect(Gate::forUser($administrator)->allows(ProductPermission::View))->toBeTrue();
    });
});
