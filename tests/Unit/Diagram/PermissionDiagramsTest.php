<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\DiagramKind;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagrams;
use Happenv\LaravelAccessControl\Facades\AccessControl;
use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\BasicRulePermission;

describe('PermissionDiagrams', function (): void {
    it('is reached through the facade and draws either kind in any format', function (): void {
        resolve(PermissionRegistry::class)->register(BasicRulePermission::class);

        $user = new User(['name' => 'Jan']);
        $user->permissions = ['basic-rule.update'];

        $diagrams = AccessControl::diagram();

        expect($diagrams)->toBeInstanceOf(PermissionDiagrams::class)
            ->and($diagrams->catalogue()->kind)->toBe(DiagramKind::Catalogue)
            ->and($diagrams->forPrincipal($user)->kind)->toBe(DiagramKind::Principal)
            ->and($diagrams->render($diagrams->forPrincipal($user), 'mermaid'))->toStartWith('flowchart LR')
            ->and($diagrams->formats())->toBe(['tree', 'mermaid', 'dot', 'json']);
    });
});
