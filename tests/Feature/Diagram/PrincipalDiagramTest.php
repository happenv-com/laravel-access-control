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
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\NamedGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\RoleHolder;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\CrossRolePermission;

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
});
