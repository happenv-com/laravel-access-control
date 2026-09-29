<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Attributes\AvailableFor;
use Happenv\LaravelAccessControl\Attributes\ConflictsWith;
use Happenv\LaravelAccessControl\Attributes\ImpliedBy;
use Happenv\LaravelAccessControl\Attributes\MutatesData;
use Happenv\LaravelAccessControl\Attributes\PermissionDescription;
use Happenv\LaravelAccessControl\Attributes\PermissionGroup;
use Happenv\LaravelAccessControl\Attributes\PermissionName;
use Happenv\LaravelAccessControl\Attributes\Requires;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionGroupDefinition;
use Happenv\LaravelAccessControl\Contracts\PermissionSurfaceDefinition;
use Happenv\LaravelAccessControl\Dto\PermissionDto;
use Happenv\LaravelAccessControl\Dto\PermissionSubjectDto;
use Happenv\LaravelAccessControl\Exceptions\PermissionGroupRequiredException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionClassConstant;

final readonly class PermissionReflector
{
    /**
     * @var array<class-string<Requires | ImpliedBy | ConflictsWith>, PermissionRuleType>
     */
    private const array RULE_ATTRIBUTES = [
        Requires::class => PermissionRuleType::Requires,
        ImpliedBy::class => PermissionRuleType::ImpliedBy,
        ConflictsWith::class => PermissionRuleType::ConflictsWith,
    ];

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
        // Read ONCE, outside the map: the class-level default is a property of the class, and
        // asking for it per case made `getSurfaces()` build a `ReflectionClass` for every case that
        // declared nothing — which is nearly all of them.
        $classDefault = $this->getClassAttribute(AvailableFor::class);

        return (new Collection($this->permission::cases()))
            ->map(fn (PermissionDefinition $case): PermissionDto => new PermissionDto(
                name: $this->getName($case->name),
                enum: $case,
                slug: $case->value,
                description: $this->getDescription($case->name),
                surfaces: $this->getSurfaces($case->name, $classDefault),
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

    /**
     * Whether exercising the permission writes data: the case's `MutatesData`, else the class's,
     * else `false`.
     *
     * The `??` is the same replacement rule as for surfaces, and it sits on the VALUE, not the
     * attribute: `#[MutatesData(false)]` on a case yields `false`, which `??` keeps, so a case can
     * opt out of a class-wide `true`.
     *
     * A case of ANOTHER enum is refused rather than read. The attribute is looked up by the case's
     * name on this reflector's enum, and permission enums share case names (`View`, `Update`) —
     * reading a stranger's case would answer confidently for its namesake.
     *
     * @throws InvalidArgumentException when the case does not belong to this reflector's enum
     */
    public function mutatesData(PermissionDefinition $permission): bool
    {
        $this->assertOwnCase($permission);

        return $this->getConstantAttribute($permission->name, MutatesData::class)->mutates
            ?? $this->getClassAttribute(MutatesData::class)->mutates
            ?? false;
    }

    /**
     * Whether the enum answers `mutatesData()` for every case by DECLARATION rather than by the
     * silent default: the class carries the attribute, or each case carries its own.
     *
     * The question an application asks to keep its catalogue classified — a new case added to a
     * partially annotated enum would otherwise read as "does not write" without anybody deciding so.
     */
    public function declaresMutatesData(): bool
    {
        if ($this->getClassAttribute(MutatesData::class) instanceof MutatesData) {
            return true;
        }

        foreach ($this->permission::cases() as $case) {
            if (! $this->getConstantAttribute($case->name, MutatesData::class) instanceof MutatesData) {
                return false;
            }
        }

        return true;
    }

    /**
     * The rules the case DECLARES — never the ones other cases declare about it; only
     * {@see PermissionGraph} sees every enum. Grouped by kind, each in declaration order, with the
     * reason as written.
     *
     * A case of another enum is refused, for the reason {@see self::mutatesData()} refuses one.
     *
     * @return list<PermissionRule>
     *
     * @throws InvalidArgumentException when the case does not belong to this reflector's enum
     */
    public function getRules(PermissionDefinition $permission): array
    {
        $this->assertOwnCase($permission);

        $constant = new ReflectionClassConstant($this->permission, $permission->name);
        $rules = [];

        foreach (self::RULE_ATTRIBUTES as $attribute => $type) {
            foreach ($constant->getAttributes($attribute) as $declaration) {
                /** @var Requires | ImpliedBy | ConflictsWith $instance */
                $instance = $declaration->newInstance();

                $rules[] = new PermissionRule($type, $permission, $instance->permission, $instance->reason);
            }
        }

        return $rules;
    }

    /**
     * Refuse a case of another enum: attributes are looked up by the case's NAME on this reflector's
     * enum, and permission enums share case names (`View`, `Update`) — reading a stranger's case
     * would answer confidently for its namesake.
     *
     * @throws InvalidArgumentException when the case does not belong to this reflector's enum
     */
    private function assertOwnCase(PermissionDefinition $permission): void
    {
        if (! $permission instanceof $this->permission) {
            throw new InvalidArgumentException(sprintf(
                'Permission %s::%s does not belong to %s.',
                $permission::class,
                $permission->name,
                $this->permission,
            ));
        }
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
    private function getSurfaces(string $enumCase, ?object $classDefault): array
    {
        $attribute = $this->getConstantAttribute($enumCase, AvailableFor::class) ?? $classDefault;

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
