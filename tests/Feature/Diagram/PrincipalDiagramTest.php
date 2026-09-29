<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Diagram\DiagramEdge;
use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\DiagramNode;
use Happenv\LaravelAccessControl\Diagram\EdgeKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\PermissionState;
use Happenv\LaravelAccessControl\Diagram\PrincipalDiagramBuilder;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Conditions\Flags;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\NamedGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\SelfAnsweringAccount;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Conditions\ConditionRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\CrossRolePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ImpliedChainPermission;

beforeEach(function (): void {
    resolve(PermissionRegistry::class)->register([BasicRulePermission::class, CrossRolePermission::class]);
});

/**
 * @return array<string, string|null> node id => state value
 */
function statesOf(PermissionDiagram $diagram): array
{
    $states = [];

    foreach ($diagram->nodes as $node) {
        $states[$node->id] = $node->state?->value;
    }

    return $states;
}

describe('PrincipalDiagramBuilder', function (): void {
    it('draws the roles a principal holds, what each stores or may act on, and the rules between', function (): void {
        $holder = new RoleHolder([
            new NamedGrantRole('Editor', ['cross-role.update']),
            new InMemoryRole(['cross-role.view']),
        ]);

        $diagram = resolve(PrincipalDiagramBuilder::class)->build($holder);

        expect($diagram->kind)->toBe(DiagramKind::Principal)
            ->and(array_map(fn (DiagramNode $node): array => [$node->id, $node->label], $diagram->nodes))->toBe([
                ['principal', 'RoleHolder'],
                ['role:0', 'Editor'],
                ['role:1', 'InMemoryRole'],
                ['permission:cross-role.view', 'View Cross Role'],
                ['permission:cross-role.create', 'Create Cross Role'],
                ['permission:cross-role.update', 'Update Cross Role'],
            ])
            ->and(statesOf($diagram))->toBe([
                'principal' => null,
                'role:0' => null,
                'role:1' => null,
                'permission:cross-role.view' => 'allowed',
                'permission:cross-role.create' => 'implied',
                'permission:cross-role.update' => 'allowed',
            ])
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to, $edge->kind], $diagram->edges))->toBe([
                ['principal', 'role:0', EdgeKind::Holds],
                ['role:0', 'permission:cross-role.update', EdgeKind::Stores],
                ['principal', 'role:1', EdgeKind::Holds],
                ['role:1', 'permission:cross-role.view', EdgeKind::Grants],
                ['permission:cross-role.create', 'permission:cross-role.view', EdgeKind::Requires],
                ['permission:cross-role.update', 'permission:cross-role.create', EdgeKind::Implies],
            ]);
    });

    it('draws the direct grants of a principal that has them', function (): void {
        $user = new User(['name' => 'Jan']);
        $user->permissions = ['basic-rule.update'];

        $diagram = resolve(PrincipalDiagramBuilder::class)->build($user);

        expect(statesOf($diagram))->toBe([
            'principal' => null,
            'direct' => null,
            'permission:basic-rule.view' => 'implied',
            'permission:basic-rule.update' => 'allowed',
            'permission:basic-rule.manage' => 'implied',
        ])
            ->and($diagram->node('principal')?->label)->toBe('Jan')
            ->and($diagram->node('direct')?->label)->toBe('direct grants')
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to, $edge->kind], $diagram->edges))->toBe([
                ['principal', 'direct', EdgeKind::Holds],
                ['direct', 'permission:basic-rule.update', EdgeKind::Stores],
                ['permission:basic-rule.update', 'permission:basic-rule.view', EdgeKind::Implies],
                ['permission:basic-rule.update', 'permission:basic-rule.manage', EdgeKind::Implies],
            ]);
    });

    it('says why a permission is not effective', function (array $grants, array $expected): void {
        $diagram = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([new InMemoryGrantRole($grants)]));

        expect(array_intersect_key(statesOf($diagram), $expected))->toBe($expected);
    })->with([
        'a requirement missing, drawn as not granted' => [['cross-role.create'], [
            'permission:cross-role.view' => 'not-granted',
            'permission:cross-role.create' => 'missing-requirement',
        ]],
        'a conflict it loses' => [['cross-role.view-own', 'cross-role.view-any'], [
            'permission:cross-role.view-own' => 'conflict',
            'permission:cross-role.view-any' => 'allowed',
        ]],
    ]);

    it('tells a restriction apart from a refusal', function (): void {
        AccessControl::restrictUsing(fn (PermissionDefinition $permission): bool => $permission === CrossRolePermission::Update);

        $restricted = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([new InMemoryGrantRole(['cross-role.update'])]));

        $refusing = new class([new InMemoryGrantRole(['cross-role.view'])]) extends RoleHolder
        {
            public function hasPermissionTo($permission): bool
            {
                return false;
            }
        };

        expect(statesOf($restricted)['permission:cross-role.update'])->toBe(PermissionState::Restricted->value)
            ->and(statesOf(resolve(PrincipalDiagramBuilder::class)->build($refusing))['permission:cross-role.view'])->toBe(PermissionState::Denied->value);
    });

    it('draws a principal using neither trait by what it answers', function (): void {
        $principal = new class implements AuthControllable
        {
            public function hasPermissionTo(PermissionDefinition $permission): bool
            {
                return $permission === BasicRulePermission::Plain;
            }
        };

        $diagram = resolve(PrincipalDiagramBuilder::class)->build($principal);

        expect(statesOf($diagram))->toBe([
            'principal' => null,
            'permission:basic-rule.plain' => 'allowed',
        ])
            ->and($diagram->node('principal')?->label)->toBe('anonymous')
            ->and($diagram->edges)->toBe([]);
    });

    it('draws an implied permission that a missing requirement blocks', function (): void {
        // The question the diagram exists for: Update implies Create, so why can they not Create?
        $diagram = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([new InMemoryGrantRole(['cross-role.update'])]));

        expect(array_intersect_key(statesOf($diagram), array_flip(['permission:cross-role.view', 'permission:cross-role.create', 'permission:cross-role.update'])))->toBe([
            'permission:cross-role.view' => 'not-granted',
            'permission:cross-role.create' => 'missing-requirement',
            'permission:cross-role.update' => 'allowed',
        ])
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to, $edge->kind], array_slice($diagram->edges, 2)))->toBe([
                ['permission:cross-role.create', 'permission:cross-role.view', EdgeKind::Requires],
                ['permission:cross-role.update', 'permission:cross-role.create', EdgeKind::Implies],
            ]);
    });

    it('draws the whole chain an implication runs along, through a permission that is not effective', function (): void {
        resolve(PermissionRegistry::class)->register(ImpliedChainPermission::class);

        $diagram = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([new InMemoryGrantRole(['implied-chain.x'])]));

        expect(array_intersect_key(statesOf($diagram), array_flip(['permission:implied-chain.x', 'permission:implied-chain.y', 'permission:implied-chain.z', 'permission:implied-chain.w'])))->toBe([
            'permission:implied-chain.x' => 'allowed',
            'permission:implied-chain.y' => 'missing-requirement',
            'permission:implied-chain.z' => 'implied',
            'permission:implied-chain.w' => 'not-granted',
        ])
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to, $edge->kind], array_slice($diagram->edges, 2)))->toBe([
                ['permission:implied-chain.y', 'permission:implied-chain.w', EdgeKind::Requires],
                ['permission:implied-chain.x', 'permission:implied-chain.y', EdgeKind::Implies],
                ['permission:implied-chain.y', 'permission:implied-chain.z', EdgeKind::Implies],
            ]);
    });

    it('does not claim a cause for what the principal allows on its own say', function (): void {
        // The administrator short-circuit: nothing stored, nothing implied, and yet allowed.
        $administrator = new class extends RoleHolder
        {
            public function hasPermissionTo($permission): bool
            {
                return true;
            }
        };

        $states = statesOf(resolve(PrincipalDiagramBuilder::class)->build($administrator));

        expect($states['permission:basic-rule.view'])->toBe('overridden')
            ->and($states['permission:cross-role.view-own'])->toBe('overridden');
    });

    it('counts a stored permission nobody registered as stored when a rule draws it', function (): void {
        resolve(PermissionRegistry::class)->register(GalleryPermission::class);

        $user = new User(['name' => 'Jan']);
        $user->permissions = ['gallery.view', 'product.view'];

        $diagram = resolve(PrincipalDiagramBuilder::class)->build($user);

        expect(statesOf($diagram)['permission:product.view'])->toBe('allowed')
            ->and(array_map(fn (DiagramEdge $edge): array => [$edge->from, $edge->to], array_slice($diagram->edges, 0, 3)))->toBe([
                ['principal', 'direct'],
                ['direct', 'permission:gallery.view'],
                ['direct', 'permission:product.view'],
            ]);
    });

    it('resolves direct grants and role grants together', function (): void {
        $user = new class(['name' => 'Jan']) extends User
        {
            protected $table = 'users';

            public function getRoles(): iterable
            {
                return [new NamedGrantRole('Editor', ['cross-role.create'])];
            }
        };
        $user->permissions = ['cross-role.view'];

        $diagram = resolve(PrincipalDiagramBuilder::class)->build($user);

        expect(array_intersect_key(statesOf($diagram), array_flip(['permission:cross-role.view', 'permission:cross-role.create'])))->toBe([
            'permission:cross-role.view' => 'allowed',
            'permission:cross-role.create' => 'allowed',
        ]);
    });

    it('numbers roles by their order, whatever they are keyed by', function (): void {
        $diagram = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([
            'admin' => new NamedGrantRole('Admin'),
            'editor' => new NamedGrantRole('Editor'),
        ]));

        expect(array_map(fn (DiagramNode $node): array => [$node->id, $node->label], array_slice($diagram->nodes, 1)))->toBe([
            ['role:0', 'Admin'],
            ['role:1', 'Editor'],
        ]);
    });

    it('asks a role that only answers hasPermissionTo(), and refuses one that cannot', function (): void {
        $answering = new class
        {
            public function hasPermissionTo($permission): bool
            {
                return $permission === CrossRolePermission::View;
            }
        };

        $drawn = resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder([$answering]));

        expect($drawn->node('role:0')?->label)->toBe('anonymous')
            ->and(statesOf($drawn)['permission:cross-role.view'])->toBe('allowed')
            ->and(fn (): PermissionDiagram => resolve(PrincipalDiagramBuilder::class)->build(new RoleHolder(['editor'])))
            ->toThrow(InvalidArgumentException::class, 'Role 0 of ' . RoleHolder::class . ' is string, which does not answer hasPermissionTo(); it cannot be drawn.');
    });
});

describe('conditions', function (): void {
    beforeEach(function (): void {
        Flags::reset();

        resolve(PermissionRegistry::class)->register(ConditionRulePermission::class);
    });

    it('draws a permission the account fails a condition of as unmet-condition', function (): void {
        $account = new InMemoryAccount(['condition-rule.alone']);

        expect(AccessControl::diagram()->forPrincipal($account)->node('permission:condition-rule.alone')?->state)
            ->toBe(PermissionState::UnmetCondition);

        Flags::raise($account);

        expect(AccessControl::diagram()->forPrincipal($account)->node('permission:condition-rule.alone')?->state)
            ->toBe(PermissionState::Allowed);
    });

    it('draws it so for an account answering hasPermissionTo() itself too', function (): void {
        expect(AccessControl::diagram()->forPrincipal(new SelfAnsweringAccount)->node('permission:condition-rule.alone')?->state)
            ->toBe(PermissionState::UnmetCondition);
    });

    it('colours the state in Mermaid', function (): void {
        $diagram = AccessControl::diagram()->forPrincipal(new InMemoryAccount(['condition-rule.alone']));

        expect(AccessControl::diagram()->render($diagram, 'mermaid'))->toContain('classDef state_unmet_condition');
    });
});
