<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Attributes\AvailableFor;
use Happenv\LaravelAccessControl\Attributes\PermissionDescription;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\PermissionName;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionGroupDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionSubjectDto;
use Happenv\LaravelAccessControl\Exceptions\PermissionGroupRequiredException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionClassConstant;

final readonly class PermissionReflector
{
    public function __construct(
        private string $permission,
    ) {}

    public function getGroup(): PermissionGroupDefinition
    {
        $reflection = new ReflectionClass($this->permission);
        $group = $reflection->getAttributes(PermissionGroup::class);

        if ($group === []) {
            throw new PermissionGroupRequiredException('PermissionGroup attribute is missing for ' . $this->permission . '.');
        }

        $attributeInstance = $group[0]->newInstance();

        return new $attributeInstance->group;
    }

    /**
     * The enum, described as the thing its permissions guard.
     */
    public function getSubject(): PermissionSubjectDto
    {
        return new PermissionSubjectDto(
            name: $this->getSubjectName(),
            slug: $this->getSubjectSlug(),
            enum: $this->permission,
            children: $this->getValues(),
            description: $this->getSubjectDescription(),
        );
    }

    /**
     * @return Collection<int,PermissionDto>
     */
    public function getValues(): Collection
    {
        return (new Collection($this->permission::cases()))
            ->map(fn (PermissionDefinition $case): PermissionDto => new PermissionDto(
                name: $this->getName($case->name),
                enum: $case,
                slug: $case->value,
                description: $this->getDescription($case->name),
                surfaces: $this->getSurfaces($case->name),
            ));
    }

    /**
     * The subject's label: a class-level `PermissionName`, else the enum's own
     * name with the `Permission` suffix dropped (`WarehousePermission` →
     * `Warehouse`).
     */
    public function getSubjectName(): string
    {
        $attribute = $this->getClassAttribute(PermissionName::class);

        return $attribute instanceof PermissionName
            ? __($attribute->value)
            : $this->getSubjectHeadline();
    }

    public function getSubjectDescription(): ?string
    {
        $attribute = $this->getClassAttribute(PermissionDescription::class);

        return $attribute instanceof PermissionDescription
            ? __($attribute->value)
            : null;
    }

    /**
     * A stable, URL- and DOM-safe handle for the subject, unique within a group
     * as long as one group does not own two enums of the same short name.
     */
    public function getSubjectSlug(): string
    {
        return Str::of($this->permission)
            ->classBasename()
            ->beforeLast('Permission')
            ->kebab()
            ->toString();
    }

    private function getDescription(string $enumCase): ?string
    {
        $attribute = $this->getConstantAttribute($enumCase, PermissionDescription::class);

        return $attribute instanceof PermissionDescription
            ? __($attribute->value)
            : null;
    }

    /**
     * The case's surfaces, else the class's, else none.
     *
     * The `??` IS the replacement rule: a case carrying the attribute never consults the class, so
     * a class-level default can be narrowed AND widened by a case without a precedence table.
     *
     * @return list<PermissionSurfaceDefinition>
     */
    private function getSurfaces(string $enumCase): array
    {
        $attribute = $this->getConstantAttribute($enumCase, AvailableFor::class)
            ?? $this->getClassAttribute(AvailableFor::class);

        return $attribute instanceof AvailableFor ? $attribute->surfaces : [];
    }

    private function getName(string $enumCase): string
    {
        $attribute = $this->getConstantAttribute($enumCase, PermissionName::class);

        return $attribute instanceof PermissionName
            ? __($attribute->value)
            : $this->getDefaultName($enumCase);
    }

    /**
     * Qualify the verb with the SUBJECT, not the group: a group is shared by
     * many enums, so qualifying with it renders every enum's `View` as the same
     * string ("View Core") with nothing to tell them apart.
     */
    private function getDefaultName(string $enumCase): string
    {
        return Str::of($enumCase)
            ->headline()
            ->append(' ')
            ->append($this->getSubjectName())
            ->toString();
    }

    private function getSubjectHeadline(): string
    {
        return Str::of($this->permission)
            ->classBasename()
            ->beforeLast('Permission')
            ->headline()
            ->toString();
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    private function getClassAttribute(string $attribute): ?object
    {
        $attributes = (new ReflectionClass($this->permission))->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    private function getConstantAttribute(string $enumCase, string $attribute): ?object
    {
        $attributes = (new ReflectionClassConstant($this->permission, $enumCase))->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
