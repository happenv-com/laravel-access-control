<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionResolutionDto;
use Happenv\LaravelAccessControl\PermissionGraph;
use Happenv\LaravelAccessControl\PermissionResolver;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ChainedConflictPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ConflictingRequirementsPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\CrossRolePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GatedScopePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ImplicationCyclePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ImpliedThroughConflictPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ImpliedThroughInactivePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\NaturalPairPermission;

describe('PermissionResolver', function (): void {
    it('resolves the worked examples of the spec', function (PermissionDefinition $permission, array $stored, bool $allowed): void {
        $resolver = resolverOver(BasicRulePermission::class, CrossRolePermission::class, GatedScopePermission::class);

        expect($resolver->allows($permission, storing(...$stored)))->toBe($allowed);
    })->with([
        'implied by a stored permission' => [BasicRulePermission::Manage, ['basic-rule.update'], true],
        'a requirement missing' => [BasicRulePermission::Create, ['basic-rule.create'], false],
        'a requirement met by an implication' => [BasicRulePermission::Create, ['basic-rule.create', 'basic-rule.update'], true],
        'the declaring side of a conflict loses' => [CrossRolePermission::ViewOwn, ['cross-role.view-own', 'cross-role.view-any'], false],
        'the other side of a conflict keeps working' => [CrossRolePermission::ViewAny, ['cross-role.view-own', 'cross-role.view-any'], true],
        'a conflict with an inactive permission decides nothing' => [GatedScopePermission::ViewOwn, ['gated-scope.view-own', 'gated-scope.view-any'], true],
    ]);

    it('resolves the multi-level examples of the spec', function (string $enum, array $stored, array $expected): void {
        $resolver = resolverOver($enum);

        foreach ($expected as $case => $allowed) {
            expect($resolver->allows(constant($enum . '::' . $case), storing(...$stored)))
                ->toBe($allowed, sprintf('%s with [%s] stored', $case, implode(', ', $stored)));
        }
    })->with([
        'M1, B stored' => [ImpliedThroughInactivePermission::class, ['m1.b'], ['A' => true, 'B' => false, 'C' => false, 'D' => false]],
        'M1, B and D stored' => [ImpliedThroughInactivePermission::class, ['m1.b', 'm1.d'], ['A' => true, 'B' => true, 'C' => true, 'D' => true]],
        'M1, D stored' => [ImpliedThroughInactivePermission::class, ['m1.d'], ['A' => false, 'B' => false, 'C' => true, 'D' => true]],
        'M2, B stored' => [ImpliedThroughConflictPermission::class, ['m2.b'], ['A' => false, 'B' => true, 'C' => false]],
        'M2, B and C stored' => [ImpliedThroughConflictPermission::class, ['m2.b', 'm2.c'], ['A' => true, 'B' => false, 'C' => true]],
        'M2, A and C stored' => [ImpliedThroughConflictPermission::class, ['m2.a', 'm2.c'], ['A' => true, 'B' => false, 'C' => true]],
        'M3, A stored' => [ImplicationCyclePermission::class, ['m3.a'], ['A' => true, 'B' => true, 'C' => true]],
        'M3, B stored' => [ImplicationCyclePermission::class, ['m3.b'], ['A' => true, 'B' => true, 'C' => true]],
        'M3, C stored' => [ImplicationCyclePermission::class, ['m3.c'], ['A' => true, 'B' => true, 'C' => true]],
        'M3, none stored' => [ImplicationCyclePermission::class, [], ['A' => false, 'B' => false, 'C' => false]],
        'M4, Delete stored' => [NaturalPairPermission::class, ['m4.delete'], ['View' => false, 'Update' => false, 'Delete' => false]],
        'M4, Update stored' => [NaturalPairPermission::class, ['m4.update'], ['View' => true, 'Update' => true, 'Delete' => false]],
        'M4, Update and Delete stored' => [NaturalPairPermission::class, ['m4.update', 'm4.delete'], ['View' => true, 'Update' => true, 'Delete' => true]],
        'M5, all stored' => [ConflictingRequirementsPermission::class, ['m5.p', 'm5.x', 'm5.y'], ['P' => true, 'X' => false, 'Y' => true]],
    ]);

    it('decides a conflict by the activity of the other permission, not by whether it is allowed', function (): void {
        // C loses its own conflict with D, but it is still ACTIVE — and activity is what P's conflict
        // reads. Reading `allowed` would let P through.
        $resolver = resolverOver(ChainedConflictPermission::class);
        $stored = storing('chained-conflict.p', 'chained-conflict.c', 'chained-conflict.d');

        expect($resolver->allows(ChainedConflictPermission::P, $stored))->toBeFalse()
            ->and($resolver->allows(ChainedConflictPermission::C, $stored))->toBeFalse()
            ->and($resolver->allows(ChainedConflictPermission::D, $stored))->toBeTrue();
    });

    it('is resolved at boot, so a long-running server keeps one graph across requests', function (): void {
        // Octane serves each request from a clone of the booted application: a singleton first
        // resolved during a request goes with the clone, and the graph would be compiled again.
        expect(app()->resolved(PermissionResolver::class))->toBeTrue()
            ->and(app()->resolved(PermissionGraph::class))->toBeTrue();
    });

    it('answers a permission without rules by asking for it once and nothing else', function (): void {
        $asked = [];

        resolverOver(BasicRulePermission::class)->allows(
            BasicRulePermission::Plain,
            function (PermissionDefinition $permission) use (&$asked): bool {
                $asked[] = $permission;

                return true;
            },
        );

        expect($asked)->toBe([BasicRulePermission::Plain]);
    });

    it('asks for each permission at most once per check', function (): void {
        // View is not stored, so its implier Update is asked again on its behalf — from the memo.
        $asked = [];

        resolverOver(NaturalPairPermission::class)->allows(
            NaturalPairPermission::Delete,
            function (PermissionDefinition $permission) use (&$asked): bool {
                $asked[] = $permission->value;

                return $permission !== NaturalPairPermission::View;
            },
        );

        expect($asked)->toBe(array_values(array_unique($asked)));
    });

    it('reads a permission whose enum is not registered like any other', function (): void {
        // GalleryPermission::View requires ProductPermission::View, whose enum is not registered here:
        // it has no rules of its own, and what is stored of it still counts.
        $resolver = resolverOver(GalleryPermission::class);

        expect($resolver->allows(GalleryPermission::View, storing('gallery.view', 'product.view')))->toBeTrue()
            ->and($resolver->allows(GalleryPermission::View, storing('gallery.view')))->toBeFalse()
            ->and($resolver->allows(ProductPermission::View, storing('product.view')))->toBeTrue();
    });

    describe('explain', function (): void {
        it('names the requirement that is missing', function (): void {
            $resolution = resolverOver(BasicRulePermission::class)
                ->explain(BasicRulePermission::Create, storing('basic-rule.create'));

            expect($resolution)->toEqual(new PermissionResolutionDto(
                allowed: false,
                stored: true,
                granted: true,
                grantedBy: [],
                missing: [BasicRulePermission::View],
                conflicting: [],
            ));
        });

        it('names what implies a permission', function (): void {
            $resolution = resolverOver(BasicRulePermission::class)
                ->explain(BasicRulePermission::View, storing('basic-rule.update'));

            expect($resolution)->toEqual(new PermissionResolutionDto(
                allowed: true,
                stored: false,
                granted: true,
                grantedBy: [BasicRulePermission::Update],
                missing: [],
                conflicting: [],
            ));
        });

        it('names the conflict a permission loses', function (): void {
            $resolution = resolverOver(CrossRolePermission::class)
                ->explain(CrossRolePermission::ViewOwn, storing('cross-role.view-own', 'cross-role.view-any'));

            expect($resolution->allowed)->toBeFalse()
                ->and($resolution->conflicting)->toBe([CrossRolePermission::ViewAny]);
        });

        it('explains a permission without rules by what is stored', function (): void {
            $resolution = resolverOver(BasicRulePermission::class)
                ->explain(BasicRulePermission::Plain, storing('basic-rule.plain'));

            expect($resolution)->toEqual(new PermissionResolutionDto(
                allowed: true,
                stored: true,
                granted: true,
                grantedBy: [],
                missing: [],
                conflicting: [],
            ));
        });
    });

    it('is one instance per application, over one graph', function (): void {
        expect(resolve(PermissionResolver::class))->toBe(resolve(PermissionResolver::class))
            ->and(resolve(PermissionGraph::class))->toBe(resolve(PermissionGraph::class));
    });
});
