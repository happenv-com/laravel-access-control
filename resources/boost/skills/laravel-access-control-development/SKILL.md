---
name: laravel-access-control-development
description: >-
  Define and check permissions with happenv-com/laravel-access-control (enum permissions + voters
  on Laravel's Gate, for modular monoliths). Use when writing a permission enum (PermissionDefinition,
  #[PermissionGroup], #[PermissionName], #[PermissionDescription], #[MutatesData], #[AvailableFor]),
  registering it (PermissionRegistry::register), writing a voter (VoterRegistry::register,
  #[VoterForPermission], Response::deny), declaring rules (#[Requires], #[ImpliedBy],
  #[ConflictsWith]), writing a PermissionCondition, setting up a User or Role model (AuthControllable,
  HasRoles, HasPermissions, HasRolesAndPermissions, HoldsGrants), restricting permissions at runtime
  (AccessControl::restrictUsing), listing effective permissions, building a permission UI
  (PermissionCollection, PermissionResolver::explainer), drawing graphs (permission:graph), or
  testing authorization (PermissionGraph::problems, Gate::forUser).
---

# Laravel Access Control

Permissions are **backed enums** implementing `PermissionDefinition`; each case is one Gate ability. A module registers its enums, and **any module** can register **voters** that may veto a permission for a given object, without the owning module knowing. A check runs: runtime restrictions → the principal's `hasPermissionTo()` (stored grants + rules between permissions) → account conditions → every voter. Voters can only deny; they never grant. Nothing writes implied grants: the library only reads what is stored.

## When to use this skill

- Adding permissions to a module, or a constraint (voter) to another module's permission.
- Wiring a `User` or `Role` model to hold grants.
- Authorizing in controllers, Livewire, Filament, Blade or jobs with permission enums.
- Declaring dependencies between permissions, account conditions (MFA, verified e-mail) or a read-only mode.
- Building or testing anything that lists or explains permissions.

For the Filament screens (roles x permissions matrix, per-record editor), see the `filament-access-control-development` skill; it builds on this one.

## Defining permissions

```php
use Happenv\LaravelAccessControl\Attributes\MutatesData;
use Happenv\LaravelAccessControl\Attributes\PermissionDescription;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\PermissionName;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;

#[PermissionGroup(CatalogueGroup::class)]
#[MutatesData]
enum ProductPermission: string implements PermissionDefinition
{
    #[PermissionName('permissions.product.view')]       // passed through __()
    #[PermissionDescription('permissions.product.view_help')]
    #[MutatesData(false)]                                // a case REPLACES the class default
    case View = 'catalogue.product.view';

    case Update = 'catalogue.product.update';            // unnamed: "Update Product"
    case Delete = 'catalogue.product.delete';
}
```

```php
use Happenv\LaravelAccessControl\Contracts\PermissionGroupDefinition;

final class CatalogueGroup implements PermissionGroupDefinition
{
    public function getName(): string { return __('permissions.groups.catalogue'); }  // NOT translated for you
    public function getDescription(): ?string { return null; }
    public function getSlug(): string { return 'catalogue'; }
}
```

- The value is what is stored in grants and what the Gate ability is called. Prefix it with the module (`catalogue.product.view`); values must be unique across the whole app (`PermissionAlreadyRegisteredException`). Changing a value orphans every stored grant: treat it like a column rename and migrate the stored data.
- One enum = one **subject** (`ProductPermission` → "Product"). Put `PermissionName` / `PermissionDescription` on the enum class to label the subject; unnamed cases become "<Case headline> <Subject>".
- Always give the enum a `#[PermissionGroup]`. Checks work without it, but `PermissionCollection` (and every permission UI built on it) throws `PermissionGroupRequiredException`. The group class is instantiated with `new`, so no constructor arguments.
- `#[AvailableFor(Surface::Api, ...)]` (class default or per case, a case replaces) tags the surfaces a permission is offered on; `Surface` is your own backed enum implementing `PermissionSurfaceDefinition`. `PermissionCollection::availableOn($surface)` returns the declared slugs.

## Registering: in `register()`, not `boot()`

```php
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\VoterRegistry;

public function register(): void
{
    $this->app->make(PermissionRegistry::class)->register([
        ProductPermission::class,
        CategoryPermission::class,
    ]);
}

public function boot(): void
{
    $this->app->make(VoterRegistry::class)->register(ProductVoter::class);
}
```

`AccessControlServiceProvider::boot()` defines one Gate ability per permission **registered at that moment**. A package-discovered provider boots before the application's providers, so an enum registered in an app provider's `boot()` gets **no Gate ability**: `can()` silently returns `false` and `@can` hides everything. Register enums in `register()` (with happenv-com/laravel-true-modular, declare them on the module, which does it in `initialize()`). Voters are looked up at check time, so `boot()` is fine for them. Registering the same enum twice throws.

## Voters

A voter receives the Gate user and the Gate arguments, and must return `Illuminate\Auth\Access\Response`. The first `deny()` wins; its message is the refusal message.

```php
use Happenv\LaravelAccessControl\Attributes\VoterForPermission;
use Illuminate\Auth\Access\Response;

final class ProductGalleryVoter
{
    #[VoterForPermission(ProductPermission::Delete)]
    public function blockWhileGalleriesExist(User $user, ?Product $product = null): Response
    {
        return $product?->galleries()->exists()
            ? Response::deny(__('gallery::errors.product_has_galleries'))
            : Response::allow();
    }
}

resolve(VoterRegistry::class)->register(ProductGalleryVoter::class);   // or [A::class, B::class]

// A closure voter:
resolve(VoterRegistry::class)->register(
    CategoryPermission::Delete,
    fn (User $user, ?Category $category = null): Response => $category?->products()->exists()
        ? Response::deny('Category still has products.')
        : Response::allow(),
);
```

- Make the subject parameter **nullable with a default**. `Gate::allows(ProductPermission::Delete)` without a model (menus, list pages) calls voters with no arguments; a required parameter throws `ArgumentCountError`.
- The voter class is created with `new` for every vote: **no constructor injection**. Resolve services inside the method (`resolve(...)`). Only public methods with `#[VoterForPermission]` are read, one attribute per method.
- Always return a `Response`, never a `bool`.
- Voters do not run in `hasPermissionTo()`, `getEffectivePermissions()` or `explain()`; only at the Gate.

## Principals and roles

The model the Gate checks (usually `User`) implements `AuthControllable`. Pick one trait:

| Trait | Use for | You implement |
|---|---|---|
| `HasRoles` | users who get permissions only through roles (recommended) | `getRoles(): iterable` |
| `HasPermissions` | roles, API keys, users with direct grants | `getPermissions(): Collection`, `setPermissions(Collection)` (must persist) |
| `HasRolesAndPermissions` | users with roles AND direct grants | all three |

```php
use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\HoldsGrants;
use Happenv\LaravelAccessControl\Traits\HasPermissions;
use Happenv\LaravelAccessControl\Traits\HasRoles;

class Role extends Model implements AuthControllable, HoldsGrants
{
    use HasPermissions;

    protected function casts(): array { return ['permissions' => 'array']; }

    protected function getPermissions(): Collection { return collect($this->permissions ?? []); }

    protected function setPermissions(Collection $permissions): void
    {
        $this->permissions = $permissions->values()->all();
        $this->save();
    }
}

class User extends Authenticatable implements AuthControllable
{
    use HasRoles;

    public function getRoles(): iterable { return $this->roles; }
}
```

- Declare `HoldsGrants` on roles (the trait already provides `getGrants()`). Rules are then resolved over the union of all roles, and checks read each role's grants once. A `HoldsGrants` role is **no longer asked `hasPermissionTo()`**: do not combine it with an overridden `hasPermissionTo()` (a disabled role would grant again).
- `HasRoles` memoises answers per model instance. After changing a user's roles or a role's grants in the same request (or test), call `$user->forgetResolvedPermissions()` or use a fresh instance.
- `givePermissionTo($enum)` / `revokePermissionTo($enum)` store exactly that value; implied permissions are never stored.
- **Stored is not effective.** `hasPermissionTo()` means "may act now" (rules, restrictions, conditions applied). An editor must read and write the stored list (`getPermissions()` / `getGrants()`), never pre-fill checkboxes from `hasPermissionTo()`, or saving during a restriction revokes grants.

## Checking

```php
Gate::authorize(ProductPermission::Delete, $product);
$user->can(ProductPermission::Update, $product);
$this->authorize(ProductPermission::View, $product);
@can(ProductPermission::View, $product) ... @endcan

$user->getEffectivePermissions();                      // Collection of enum cases, voters NOT applied
AccessControl::effectivePermissions($principal);       // same for any AuthControllable
```

- Pass the **enum**, not the string. `HasPermissions::hasPermissionTo()` accepts only a `PermissionDefinition` (a string is a `TypeError`); `HasRoles` and `HasRolesAndPermissions` accept a string but then skip rules and restrictions (conditions still apply). The Gate resolves a string to the same ability, so `Gate::allows('...')` is fine.
- Refusals read `Unauthorized.` for a missing grant, restriction and unmet condition alike. `config('access-control.display_permission_in_exception')` makes it `Unauthorized for <value>` (publish: `--tag=access-control-config`). Only a voter's `deny()` carries its own message.
- **Super-admin.** A `Gate::before()` returning `true` skips restrictions, conditions **and voters**. To keep them for admins, short-circuit in the model instead: `use HasRoles { hasPermissionTo as roleHasPermissionTo; }` and `public function hasPermissionTo(PermissionDefinition $permission): bool { return $this->is_admin || $this->roleHasPermissionTo($permission); }`. The Gate still applies restrictions, conditions and voters to the admin; only grants and rules are skipped. Outside the Gate, ask `AccessControl::isRestricted()` yourself.

## Rules between permissions

```php
use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\Requires;

enum GalleryPermission: string implements PermissionDefinition
{
    #[Requires(ProductPermission::View)]                                // only while View is active (granted, its own requirements met)
    case View = 'gallery.view';

    #[ImpliedBy(ProductPermission::Update, reason: 'permissions.rules.follows')]  // granted with Update
    case Manage = 'gallery.manage';

}

enum OrderPermission: string implements PermissionDefinition
{
    #[ConflictsWith(self::ViewAny)]                                     // not allowed while ViewAny is active
    case ViewOwn = 'order.view-own';

    case ViewAny = 'order.view-any';
}
```

- `Requires` and `ConflictsWith` look at whether the other permission is **active**: granted (stored or implied) with its own requirements met. They do not see the other permission's conflicts, runtime restrictions, account conditions or voters, so a `Requires(View)` case stays allowed while `View` itself is blocked by one of those. Guard the dependent permission with its own condition, restriction or voter if that matters.
- A rule changes **only the case it sits on**: declare it in the module that knows the other permission. Only the declaring side of a conflict loses.
- Several `#[Requires]` = all required. `#[ImpliedBy]` follows the *grant*, so `Update #[Requires(View)]` with `View #[ImpliedBy(Update)]` works.
- Restrictions withhold the permission asked, not what it implies. The voters of the checked permission run, not those of its implier.
- A self-reference or a `Requires` cycle throws `InvalidPermissionRuleException` at the first check. Contradictions and rules pointing at unregistered enums are reported by `PermissionGraph::problems()`; assert it is empty in a test.
- `reason` is a translation key (gets `:permission` and `:other`), shown only in UIs.

## Runtime restrictions

Withhold permissions from everyone while something holds (read-only mode, suspended tenant); grants are untouched.

```php
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionReflector;

// In a service provider's boot():
AccessControl::restrictUsing(
    fn (PermissionDefinition $permission): bool => ReadOnlyMode::isActive()
        && (new PermissionReflector($permission::class))->mutatesData($permission),
);
```

The closure runs on every check and is never cached (Octane-safe): keep it cheap and read state fresh, never capture it at boot. Any closure returning truthy restricts. `AccessControl::isRestricted($p)` asks directly. `PermissionReflector::declaresMutatesData()` tells whether every case is classified; assert it in a test if the catalogue must be.

## Account conditions

An attribute class implementing `PermissionCondition`, placed on an enum (all cases) or a case. Nothing to register.

```php
use Attribute;
use Happenv\LaravelAccessControl\Contracts\DescribesPermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionCondition;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class RequiresVerifiedEmail implements PermissionCondition, DescribesPermissionCondition
{
    public function check(PermissionDefinition $permission, Authenticatable $principal): bool
    {
        return $principal instanceof MustVerifyEmail && $principal->hasVerifiedEmail();
    }

    public function describe(): string { return __('permissions.conditions.verified_email'); }
}
```

- Applies only to `Authenticatable` principals (not to roles). Checked last, never cached, run up to twice per Gate check: no side effects, avoid queries.
- **Import the attribute class** in the enum's file. PHP silently ignores an unresolvable attribute, and the permission is then granted without the condition. Cover each condition with a test.
- `AccessControl::unmetConditions($permission, $account)` lists what an account fails. Filament ships `#[RequiresMFA]`.

## Permission UIs and introspection

```php
$collection = resolve(PermissionCollection::class);
$collection->getGroupedPermissions();   // Collection<slug, PermissionGroupDto> with ->children and ->subjects
$collection->getPermissions();          // flat PermissionDto list: name, enum, slug, description, surfaces, rules, conditions

$explain = resolve(PermissionResolver::class)->explainer(
    AccessControl::storedGrantsOf($user),   // or a closure over the form's unsaved state
    $user,                                   // the account, for conditions; omit for a role
);
$dto = $explain(GalleryPermission::Manage); // allowed, stored, granted, grantedBy, missing, conflicting, restricted, unmetConditions, effective
```

`PermissionGraph::problemDetails()` returns `PermissionProblemDto`s for display. Diagrams: `php artisan permission:graph [principal-key] --format=tree|mermaid|dot|json [--guard=] [--model=]`, or `AccessControl::diagram()->forPrincipal($user)` / `->catalogue()` with `->render($diagram, 'mermaid')`. Implement `DescribesGrantHolder` on roles to name them in diagrams.

## Testing

There are no fakes; test through the real Gate.

```php
it('declares sound permission rules', function (): void {
    expect(resolve(PermissionGraph::class)->problems())->toBe([]);
});

it('blocks deleting a product with galleries', function (): void {
    $user = User::factory()->withRole('editor')->create();   // your own setup
    $product = Product::factory()->hasGalleries()->create();

    expect(Gate::forUser($user)->inspect(ProductPermission::Delete, $product)->message())
        ->toBe(__('gallery::errors.product_has_galleries'));
});
```

- Enums registered by the app are already registered in tests; registering them again throws. An enum registered only inside a test needs `resolve(GateConfigurator::class)->configure()` afterwards to get its Gate ability.
- Restrictions added with `restrictUsing()` stay for the life of the application instance; register them in the test that needs them.

## Do not

- Do not register permission enums in `boot()` of an application provider.
- Do not give voters required model parameters, constructor dependencies, or `bool` returns.
- Do not check with value strings where you mean the enum.
- Do not read `hasPermissionTo()` to decide what is stored, or store implied permissions yourself.
- Do not use `Gate::before()` for super-admins if voters or restrictions must still apply to them.
- Do not use `AccessControl::reflectPermission()`; it is an empty stub.
