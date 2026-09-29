<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Exceptions\InvalidPermissionRuleException;
use Happenv\LaravelAccessControl\PermissionGraph;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\DuplicateRulePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ImplicationCyclePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\ProblemPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\RequirementCyclePermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\SelfReferencingPermission;

describe('PermissionGraph', function (): void {
    describe('indexing', function (): void {
        it('knows which permissions declare rules', function (): void {
            $graph = graphOver(ProductPermission::class, GalleryPermission::class);

            expect($graph->declaresRules(GalleryPermission::View))->toBeTrue()
                // The TARGET of a rule declares nothing: a rule changes only the permission declaring it.
                ->and($graph->declaresRules(ProductPermission::View))->toBeFalse();
        });

        it('lists what a permission requires, is implied by and conflicts with', function (): void {
            $graph = graphOver(ProductPermission::class, GalleryPermission::class);

            expect($graph->requirements(GalleryPermission::View))->toBe([ProductPermission::View])
                ->and($graph->directImpliers(GalleryPermission::Manage))->toBe([ProductPermission::Update])
                ->and($graph->impliers(GalleryPermission::Manage))->toBe([ProductPermission::Update])
                ->and($graph->conflicts(GalleryPermission::Archive))->toBe([ProductPermission::Delete])
                ->and($graph->requirements(ProductPermission::View))->toBe([]);
        });

        it('puts the same rule on both of its ends', function (): void {
            $graph = graphOver(ProductPermission::class, GalleryPermission::class);

            [$declared] = $graph->rulesTouching(GalleryPermission::View);

            expect($graph->rulesTouching(ProductPermission::View))->toBe([$declared])
                ->and($declared->permission)->toBe(GalleryPermission::View)
                ->and($declared->other)->toBe(ProductPermission::View);
        });

        it('counts a rule declared twice once', function (): void {
            $graph = graphOver(DuplicateRulePermission::class);

            expect($graph->requirements(DuplicateRulePermission::Edit))->toBe([DuplicateRulePermission::View])
                ->and($graph->rulesTouching(DuplicateRulePermission::Edit))->toHaveCount(1);
        });
    });

    describe('implication closure', function (): void {
        it('is a set: a cycle of three yields the other two, each once', function (): void {
            $impliers = array_map(
                fn (PermissionDefinition $permission): string => $permission->name,
                graphOver(ImplicationCyclePermission::class)->impliers(ImplicationCyclePermission::A),
            );

            sort($impliers);

            expect($impliers)->toBe(['B', 'C']);
        });
    });

    describe('declaration errors', function (): void {
        it('refuses a rule about the permission itself', function (): void {
            expect(fn (): bool => graphOver(SelfReferencingPermission::class)->declaresRules(SelfReferencingPermission::Loop))
                ->toThrow(InvalidPermissionRuleException::class, 'about itself');
        });

        it('refuses permissions that require each other in a cycle', function (): void {
            expect(fn (): bool => graphOver(RequirementCyclePermission::class)->declaresRules(RequirementCyclePermission::A))
                ->toThrow(InvalidPermissionRuleException::class, 'cycle');
        });

        it('compiles lazily, so registering a broken enum does not throw by itself', function (): void {
            expect(fn (): PermissionGraph => graphOver(RequirementCyclePermission::class))->not->toThrow(InvalidPermissionRuleException::class);
        });

        it('throws again on the next check rather than answer from half an index', function (): void {
            $graph = graphOver(RequirementCyclePermission::class);

            expect(fn (): bool => $graph->declaresRules(RequirementCyclePermission::A))->toThrow(InvalidPermissionRuleException::class)
                ->and(fn (): bool => $graph->declaresRules(RequirementCyclePermission::A))->toThrow(InvalidPermissionRuleException::class);
        });
    });

    describe('compilation', function (): void {
        it('recompiles after a late registration', function (): void {
            $registry = new PermissionRegistry;
            $registry->register(ProductPermission::class);
            $graph = new PermissionGraph($registry);

            expect($graph->declaresRules(GalleryPermission::View))->toBeFalse();

            // A module whose provider booted after the first check.
            $registry->register(GalleryPermission::class);

            expect($graph->declaresRules(GalleryPermission::View))->toBeTrue();
        });
    });

    describe('problems', function (): void {
        it('reports exactly the permissions that can never be allowed', function (ProblemPermission $permission, bool $reported): void {
            $prefix = ProblemPermission::class . '::' . $permission->name . ' ';

            $about = array_filter(
                graphOver(ProblemPermission::class)->problems(),
                fn (string $problem): bool => str_starts_with($problem, $prefix),
            );

            expect($about !== [])->toBe($reported);
        })->with([
            'requires what it conflicts with' => [ProblemPermission::DirectP, true],
            'requires it transitively' => [ProblemPermission::TransitiveP, true],
            'implies what it conflicts with' => [ProblemPermission::ImpliesP, true],
            'implies it transitively' => [ProblemPermission::ChainP, true],
            'implies it, but the implied one may be inactive' => [ProblemPermission::GatedP, false],
            'its requirements conflict with each other' => [ProblemPermission::SplitP, false],
            'is implied by what it conflicts with' => [ProblemPermission::ReverseP, false],
            'points at an enum nobody registered' => [ProblemPermission::OrphanP, true],
        ]);

        it('reports nothing about a sound catalogue', function (): void {
            expect(graphOver(ProductPermission::class, GalleryPermission::class)->problems())->toBe([]);
        });
    });
});
