<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Happenv\LaravelAccessControl\PermissionCollection;
use Happenv\LaravelAccessControl\PermissionRuleType;

/**
 * The rules of the whole catalogue: every registered permission in its group and subject, and every
 * rule between them.
 */
final readonly class CatalogueDiagramBuilder
{
    public function __construct(
        private PermissionCollection $collection,
    ) {}

    public function build(): PermissionDiagram
    {
        $catalogue = new DiagramCatalogue($this->collection);
        $draft = new DiagramDraft(DiagramKind::Catalogue);

        foreach ($catalogue->permissions() as $permission) {
            $catalogue->addPermission($draft, $permission->enum, null);
        }

        // A rule may point at a permission nobody registered — drawn after the catalogue, outside it.
        foreach ($catalogue->permissions() as $permission) {
            foreach ($catalogue->declaredTargets($permission->enum, ...PermissionRuleType::cases()) as $target) {
                $catalogue->addPermission($draft, $target, null);
            }
        }

        $catalogue->addRuleEdges($draft);

        return $draft->toDiagram();
    }
}
