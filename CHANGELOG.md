# Changelog

All notable changes to `laravel-access-control` are documented in this file. Each section is written automatically from the GitHub release notes when a release is published — do not edit it by hand.

## 3.1.1 - Unreleased

### Fixed

- `hasPermissionTo()` with an ability string threw a `TypeError` when a role of the principal used `HasPermissions` without `HoldsGrants`: such a role answers only a permission enum. Its stored grants are read instead.
- `HasRolesAndPermissions::hasPermissionTo()` with an ability string granted through a role checked the permission's conditions twice.

## 3.1.0 - 2026-09-29

### Added

- Conditions on accounts: an attribute implementing `Contracts\PermissionCondition` (`check($permission, $account): bool`), on a permission enum or case, withholds the permission from an `Authenticatable` principal that does not meet it — at the gate, in `hasPermissionTo()` of the traits and in `effectivePermissions()`. `hasPermissionTo()` called with an ability string applies the conditions of the registered permission it names too. Roles and other principals that do not sign in are never evaluated. `Contracts\DescribesPermissionCondition` names a condition for UIs.
- `PermissionConditions`, a singleton that finds conditions by interface and keeps the attribute instances per process; `AccessControl::unmetConditions($permission, $principal)`.
- `PermissionResolver::explainer($stored, ?$account)`: one resolution answering many permissions. `PermissionResolutionDto` gains `restricted`, `unmetConditions` and `effective`.
- `AccessControl::storedGrantsOf($principal)` and `roleGrantsOf($principal)`: what a principal stores, for a UI to put staged changes on.
- `PermissionDto::$conditions`; `PermissionGraph::problemDetails()` with `PermissionProblemDto` and `PermissionProblemType`.
- Principal diagrams: the node state `unmet-condition`.

### Changed

- `effectivePermissions()` leaves out restricted permissions for every principal — before, a principal answering `hasPermissionTo()` itself could list a restricted one the gate refused.
- The diagram JSON schema is version 2 by default (a new state value, `unmet-condition`); `permission:graph --schema-version` defaults to 2. `--schema-version=1` still works, drawing an unmet condition as `denied` — how 3.0 drew a permission the principal's own check refused.
- `explain()` fills the new `restricted` field.

**Upgrading:** nothing changes until a permission carries a condition. A consumer of the diagram JSON that checks the schema version must accept 2, or keep requesting version 1.

## 3.0.0 - 2026-09-29

### Added

- Rules between permissions, declared on enum cases (#23):
  - `#[Requires(A)]`: effective only while A is;
  - `#[ImpliedBy(A)]`: granted along with A;
  - `#[ConflictsWith(A)]`: not effective while A is.

  Each is repeatable and takes an optional `reason`: a translation key, or on PHP 8.5+ a closure. A rule changes only the permission that declares it.
- `PermissionGraph`, a singleton that compiles the rules of every registered enum lazily, once per process, and recompiles after a later registration.
  - A rule about the permission itself, or a cycle of `Requires`, throws `InvalidPermissionRuleException`.
  - `problems()` lists permissions that can never be allowed and rules pointing at unregistered enums.
- `PermissionResolver`, a singleton, with `allows($permission, $stored)` and `explain($permission, $stored)`. `explain()` returns a `PermissionResolutionDto` for a UI.
- `Contracts\HoldsGrants`: a role that hands over its raw grants (`getGrants()`, provided by `HasPermissions`). A principal then resolves rules over the union of its roles and reads their grants once per instance.
- `PermissionDto::$rules`: every `PermissionRuleDto` a permission declares or is the target of. `PermissionCollection` attaches them with the reason translated.
- `getEffectivePermissions()` on `HasPermissions`, `HasRoles` and `HasRolesAndPermissions`, and `AccessControl::effectivePermissions($principal)` for any `AuthControllable`: every registered permission the principal may act on now, as a `Collection` of enum cases. Rules and restrictions count; voters do not.
- `PermissionReflector::getRules()`.
- Permission graphs:
  - `php artisan permission:graph` draws the catalogue's rules, and `permission:graph {key}` draws what a principal may do and why. It takes `--format=tree|mermaid|dot|json`, `--model`, `--guard` and `--schema-version`.
  - The same graphs are available in code through `AccessControl::diagram()`: `catalogue()`, `forPrincipal($user)`, `render($diagram, $format)` and `toArray()`.
  - `Contracts\DescribesGrantHolder` names a principal or role. Renderers tagged `access-control.diagram-renderers` add or replace formats.

### Changed

- `hasPermissionTo()` of `HasPermissions`, `HasRoles` and `HasRolesAndPermissions` applies the rules. A permission that declares none is answered as before.
- `HasRoles` reads the grants of `HoldsGrants` roles once per instance. `forgetResolvedPermissions()` clears them too. Other roles are asked `hasPermissionTo()` as before.
- `HasRolesAndPermissions` resolves once over the union of direct and role grants, instead of asking each source separately.
- `PermissionCollection` takes an optional `PermissionGraph` as a second constructor argument.

### Fixed

- `HasRolesAndPermissions::hasPermissionTo()` threw when given an ability string or a backed enum that is not a permission. It now checks the direct grants and the roles by value, as `HasRoles` does.

**Upgrading:** nothing changes until a permission declares a rule. To have rules resolved across roles, and for the faster lookup, add `implements HoldsGrants` to role classes that use `HasPermissions`. A `HoldsGrants` role is no longer asked `hasPermissionTo()`, so a role class that overrides it (a super-admin role, a role that can be switched off) must keep that logic in `getGrants()` or stay off `HoldsGrants`.

## 2.3.0 - 2026-09-28

### Added

- `#[MutatesData]` — declares whether exercising a permission writes data. On the enum class it is the default for every case; a case carrying its own replaces it (`#[MutatesData(false)]` opts a case out). A permission declaring nothing reads as not writing.
- `PermissionReflector::mutatesData(PermissionDefinition)` — the case's declaration, else the class's, else `false`. A case of another enum is refused with `InvalidArgumentException` instead of being answered for its namesake.
- `PermissionReflector::declaresMutatesData()` — whether every case is answered by a declaration (the class carries the attribute, or each case does), for applications that want their whole catalogue classified.
- Runtime restrictions: `PermissionRestrictions` (a singleton), with `AccessControl::restrictUsing(Closure)` and `AccessControl::isRestricted(PermissionDefinition)` on the class and the facade. A restriction withholds a permission from every principal while its closure returns `true`, without touching anybody's grants. The closures are asked on every check and nothing is cached, so a restriction is correct under Laravel Octane.

### Changed

- The Gate refuses a restricted permission for every authenticated principal, before its `hasPermissionTo()`, with the SAME message as a missing permission (`Unauthorized.` / `Unauthorized for <permission>`). Guests are still told `Unauthenticated.`.
- `HasPermissions::hasPermissionTo()` and `HasRoles::hasPermissionTo()` answer `false` for a restricted permission. `HasRoles` asks before its per-instance memo, so a restriction that begins or ends mid-request is honoured. Ability strings are not restricted.
- `HasPermissions::givePermissionTo()` deduplicates by the STORED grants instead of `hasPermissionTo()`. Under a restriction the old check pushed the same grant again on every call; on a model using `HasRolesAndPermissions` it also skipped a direct grant that a role already provided — that grant is now stored.
- `GateConfigurator` takes `PermissionRestrictions` as a third constructor argument.

**Upgrading:** nothing to do for applications that resolve `GateConfigurator` from the container (the package's provider does). Code relying on `givePermissionTo()` skipping a permission a role already grants will now store the direct grant.

## 2.1.1 - 2026-09-06

### Fixed

- The refusal returned when the gate is handed NO user said `Unauthenicated.` — a misspelling of `Unauthenticated.` carried since the initial release. Consumers publish this message verbatim (Sellero's public GraphQL API puts it straight into its error envelope), so the typo was reaching partner integrations. `Unauthorized.`, the different refusal for a principal that lacks the ability, is unchanged — the two are separate problems on the caller's side and both are now pinned by a test.

**Upgrading:** anything asserting or comparing against the old spelling has to change with it.

## 2.1.0 - 2026-09-03

### Added

- `PermissionSubjectDto` — the level between a group and its permissions, one subject per permission enum. A group answers which module owns a permission; a subject answers what the permission is about.
- `PermissionGroupDto::$subjects` — the group's permissions grouped by the enum that declares them, keyed by that enum's class name. `$children` is unchanged and still holds the flat list.
- `PermissionCollection::getSubjects()` — every subject across every group.
- `PermissionReflector::getSubject()`, `getSubjectName()`, `getSubjectDescription()` and `getSubjectSlug()`.
- `PermissionName` and `PermissionDescription` are now read from the enum CLASS as well as from its cases, where they label the subject. Without one, the subject is named after the enum with the `Permission` suffix dropped (`WarehousePermission` → `Warehouse`).

### Changed

- The default name of a permission without its own `PermissionName` is now qualified with its SUBJECT instead of its group (`Delete Product`, not `Delete Products`). A group shared by several enums rendered every one of their `Delete` cases as the same unusable string.

## 1.0.0 - 202X-XX-XX

- initial release
