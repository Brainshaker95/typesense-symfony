<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Exception\InvalidPropertyException;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Exception\UnreachableException;
use App\Search\Model\Attribute\Field as AttributeField;
use App\Search\Model\Field;
use App\Search\Model\Schema;
use App\Search\Model\SearchContext;
use App\Search\Model\Support\ArrayableTrait;
use Override;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\String\AbstractString;
use Throwable;
use TypeError;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function class_exists;
use function count;
use function enum_exists;
use function implode;
use function in_array;
use function interface_exists;
use function is_bool;
use function is_int;
use function is_string;
use function preg_quote;
use function property_exists;
use function sprintf;
use function Symfony\Component\String\s;
use function usort;

/**
 * @template TArrayRepresentation of array<non-empty-string, mixed> = array<non-empty-string, mixed>
 */
trait CollectionTrait
{
    /**
     * @use ArrayableTrait<TArrayRepresentation>
     */
    use ArrayableTrait {
        ArrayableTrait::toArray as private defaultToArray;
    }

    private const RESERVED_PROPERTY_NAMES = [
        'schema',
    ];

    private static Schema $schema;

    /**
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    #[Override]
    public static function getSchema(): Schema
    {
        return new Schema(
            name: self::getSchemaNameForThisClassName(),
            fields: self::getFields(),
            default_sorting_field: self::getDefaultSortingField(),
        );
    }

    /**
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    public static function getSearchParameters(SearchContext $searchContext): array
    {
        $queryBy    = self::getQueryBy();
        $sortBy     = self::getSortBy();
        $parameters = [];

        if ($queryBy !== null) {
            $parameters['query_by'] = $queryBy;
        }

        if ($sortBy !== null) {
            $parameters['sort_by'] = $sortBy;
        }

        return $parameters;
    }

    /**
     * @throws InvalidPropertyException
     */
    public function getTypesenseId(): string
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        return property_exists($this, 'id') && is_string($this->id)
            ? $this->id
            : throw new InvalidPropertyException(sprintf(
                'Class "%s" does not expose a string "id" property and has not overridden the "getTypesenseId" method. Add a public string $id property to the class or implement a custom "getTypesenseId" method.',
                $this::class,
            ));
    }

    /**
     * @throws Throwable
     * @throws TypeError
     */
    public static function fromArray(array $data): self
    {
        $reflection = new ReflectionClass(self::class);
        $arguments  = [];

        $properties = array_filter(
            $reflection->getProperties(),
            static fn (ReflectionProperty $property): bool => !$property->isPromoted(),
        );

        $promotedProperties = array_filter(
            $reflection->getProperties(),
            static fn (ReflectionProperty $property): bool => $property->isPromoted(),
        );

        foreach ($promotedProperties as $promotedProperty) {
            $name = $promotedProperty->getName();

            if (array_key_exists($name, $data)) {
                self::assertAllowedPropertyName($name);

                $arguments[$name] = $data[$name];
            } elseif (self::isReservedPropertyName($name)) {
                continue;
            } elseif ($promotedProperty->hasDefaultValue()) {
                $arguments[$name] = $promotedProperty->getDefaultValue();
            } elseif ($promotedProperty->getType()?->allowsNull() ?? false) {
                $arguments[$name] = null;
            }
        }

        /** @phpstan-ignore-next-line argument.type (This function is allowed to throw on type errors) */
        $instance = new self(...$arguments);

        foreach ($properties as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                self::assertAllowedPropertyName($name);

                $property->setValue($instance, $data[$name]);
            } elseif (self::isReservedPropertyName($name)) {
                continue;
            } elseif ($property->hasDefaultValue()) {
                $property->setValue($instance, $property->getDefaultValue());
            } elseif ($property->getType()?->allowsNull() ?? false) {
                $property->setValue($instance, null);
            }
        }

        return $instance;
    }

    /**
     * @throws InvalidPropertyException
     */
    #[Override]
    public function toArray(): array
    {
        return array_merge($this->defaultToArray(), [
            'id' => $this->getTypesenseId(),
        ]);
    }

    /**
     * @return list<Field>
     *
     * @throws InvalidPropertyException
     */
    private static function getFields(): array
    {
        $fields = [];

        foreach (self::getFieldAttributes() as $fieldAttribute) {
            [
                'property' => $property,
                'instance' => $instance,
            ] = $fieldAttribute;

            $propertyName = $property->getName();
            $type         = $instance->type ?? self::guessTypesenseType($property);

            $sort = match ($type) {
                Field::TYPE_FLOAT, Field::TYPE_INT32, Field::TYPE_INT64 => $instance->sort === false
                    ? false
                    : null,
                default => is_string($instance->sort) || (is_bool($instance->sort) && $instance->sort) || is_int($instance->sortPriority) || $instance->isDefaultSortingField === true
                    ? true
                    : null,
            };

            $fields[] = new Field(
                name: $propertyName,
                type: $type,
                optional: $property->getType()?->allowsNull() ?: null,
                sort: $sort,
            );
        }

        return $fields;
    }

    /**
     * @param key-of<TArrayRepresentation> $fieldName
     */
    private static function getFieldName(string $fieldName): string
    {
        return $fieldName;
    }

    /**
     * @return ?non-empty-string
     *
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    private static function getQueryBy(): ?string
    {
        $attributes = array_values(array_filter(
            self::getFieldAttributes(),
            static fn (array $attribute): bool => $attribute['instance']->query === true,
        ));

        usort(
            $attributes,
            static fn (array $attribute1, array $attribute2): int => ($attribute2['instance']->queryPriority ?? 0) <=> ($attribute1['instance']->queryPriority ?? 0),
        );

        $queryBy = array_map(
            static fn (array $attribute): string => $attribute['property']->getName(),
            $attributes,
        );

        return implode(',', $queryBy) ?: throw new InvalidSchemaException(sprintf(
            'Class "%s" does not define any queryable fields.',
            self::class,
        ));
    }

    /**
     * @return ?non-empty-string
     *
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    private static function getSortBy(): ?string
    {
        $attributes = self::getFieldAttributes();

        usort(
            $attributes,
            static fn (array $attribute1, array $attribute2): int => ($attribute2['instance']->sortPriority ?? 0) <=> ($attribute1['instance']->sortPriority ?? 0),
        );

        $sortBy = [];
        $count  = 0;

        foreach ($attributes as $attribute) {
            [
                'property' => $property,
                'instance' => $instance,
            ] = $attribute;

            $type = $instance->type ?? self::guessTypesenseType($property);

            $isSortable = match ($type) {
                Field::TYPE_FLOAT, Field::TYPE_INT32, Field::TYPE_INT64 => $instance->sort !== false,
                default => (is_string($instance->sort) || (is_bool($instance->sort) && $instance->sort) || is_int($instance->sortPriority)),
            };

            if ($isSortable) {
                $direction = is_string($attribute['instance']->sort) ? $attribute['instance']->sort : 'desc';
                $sortBy[]  = $attribute['property']->getName() . ':' . $direction;
                $count += 1;
            }

            if ($count > 3) {
                throw new InvalidSchemaException(sprintf(
                    'Class "%s" defines to many sortable fields; only 3 are allowed. Property "%s" would be the fourth sortable field.',
                    self::class,
                    $attribute['property']->getName(),
                ));
            }
        }

        $index               = 0;
        $defaultSortingField = self::getDefaultSortingField();

        while ($count < 3) {
            if ($index === 0) {
                $sortBy = ['_text_match:desc', ...$sortBy];
            } elseif ($defaultSortingField !== null) {
                $sortBy[] = $defaultSortingField . ':desc';
            }

            $count += 1;

            if ($index === 1) {
                break;
            }

            $index += 1;
        }

        return implode(',', $sortBy) ?: null;
    }

    /**
     * @return ?non-empty-string
     *
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    private static function getDefaultSortingField(): ?string
    {
        $attributes          = self::getFieldAttributes();
        $defaultSortingField = null;
        $count               = 0;

        foreach ($attributes as $attribute) {
            [
                'property' => $property,
                'instance' => $instance,
            ] = $attribute;

            if ($instance->isDefaultSortingField === true) {
                $count += 1;

                if ($count > 1) {
                    throw new InvalidSchemaException(sprintf(
                        'Class "%s" defines more than one default sorting field; only one is allowed.',
                        self::class,
                    ));
                }

                $defaultSortingField = $property->getName();
            }
        }

        return $defaultSortingField ?: null;
    }

    /**
     * @return list<array{
     *     property: ReflectionProperty,
     *     instance: AttributeField,
     * }>
     *
     * @throws InvalidPropertyException
     */
    private static function getFieldAttributes(): array
    {
        $class           = new ReflectionClass(self::class);
        $fieldAttributes = $class->getAttributes(AttributeField::class);
        $properties      = $class->getProperties();
        $results         = [];

        foreach ($properties as $property) {
            if (self::isReservedPropertyName($property->getName())) {
                continue;
            }

            $fieldAttributes = $property->getAttributes(AttributeField::class);

            if (!isset($fieldAttributes[0])) {
                continue;
            }

            if (count($fieldAttributes) > 1) {
                throw new InvalidPropertyException(sprintf(
                    'Property "%s" of class "%s" has multiple "%s" attributes; only one is allowed.',
                    $property->getName(),
                    $class->getName(),
                    AttributeField::class,
                ));
            }

            [$fieldAttribute]       = $fieldAttributes;
            $fieldAttributeInstance = $fieldAttribute->newInstance();

            $results[] = [
                'property' => $property,
                'instance' => $fieldAttributeInstance,
            ];
        }

        return $results;
    }

    /**
     * @return Field::TYPE_*
     */
    private static function guessTypesenseType(ReflectionProperty $property): string
    {
        $typeName = self::getTypeNameFromDocComment($property);

        if ($typeName->isEmpty()) {
            if ($property->getType() instanceof ReflectionNamedType) {
                $typeName = s($property->getType()->getName())->lower();
            }

            if ($typeName->isEmpty()) {
                return Field::TYPE_AUTO;
            }
        }

        $primitiveTypes = [
            'int'    => Field::TYPE_INT64,
            'float'  => Field::TYPE_FLOAT,
            'bool'   => Field::TYPE_BOOL,
            'string' => Field::TYPE_STRING,
            'object' => Field::TYPE_OBJECT,
        ];

        $typeName = $typeName->toString();

        if (array_key_exists($typeName, $primitiveTypes)) {
            return $primitiveTypes[$typeName];
        }

        // @phpstan-ignore-next-line symplify.forbiddenFuncCall
        if (class_exists($typeName) || interface_exists($typeName) || enum_exists($typeName)) {
            return Field::TYPE_OBJECT;
        }

        $typeName = s($typeName);

        if ($typeName->startsWith('array{')) {
            return $typeName->endsWith('[]')
                ? Field::TYPE_OBJECT_ARRAY
                : Field::TYPE_OBJECT;
        }

        if (!$typeName->endsWith('[]')
            && !$typeName->startsWith('array<')
            && !$typeName->startsWith('non-empty-array<')
            && !$typeName->startsWith('non-empty-list<')) {
            return Field::TYPE_AUTO;
        }

        if ($typeName->match('/^array\{.+\}$/m') !== []) {
            return Field::TYPE_OBJECT_ARRAY;
        }

        $matches    = $typeName->match('/^(?:array|non-empty-array|list|non-empty-list)<\s*([^\s,>]+)(?:\s*,\s*[^\s>]+)?\s*>/m');
        $typeName   = is_string($matches[1] ?? null) ? s($matches[1]) : $typeName;
        $firstMatch = s(is_string($matches[0] ?? null) ? $matches[0] : '');

        if ($firstMatch->containsAny(',')
            && in_array($typeName->toString(), ['array-key', 'int'], true)) {
            $typeName = $firstMatch->trimEnd('>')->afterLast(',')->trim();

            if ($typeName->endsWith('[]')) {
                return Field::TYPE_OBJECT;
            }
        }

        return [
            'int'    => Field::TYPE_INT64_ARRAY,
            'float'  => Field::TYPE_FLOAT_ARRAY,
            'bool'   => Field::TYPE_BOOL_ARRAY,
            'string' => Field::TYPE_STRING_ARRAY,
        ][$typeName->trimEnd('[]')->toString()] ?? Field::TYPE_OBJECT_ARRAY;
    }

    private static function getTypeNameFromDocComment(ReflectionProperty $property): AbstractString
    {
        $docComment = s($property->getDocComment() ?: '');
        $hasVarTag  = $docComment->match('/@var\s+[^\s].+/m') !== [];

        if (!$hasVarTag && $property->isPromoted()) {
            $docComment = s($property->getDeclaringClass()->getConstructor()?->getDocComment() ?: $docComment->toString());
            $matches    = $docComment->match(sprintf('/@param\s+([^\s]+)\s+\$%s\b/m', preg_quote($property->getName(), '/')));

            if (is_string($matches[1] ?? null)) {
                $docComment = s('@param ' . $matches[1]);
            }
        }

        $matches = $docComment->match('/@(var|param)\s+([^\s].+)/m');

        return s(is_string($matches[2] ?? null) ? $matches[2] : '')->trim();
    }

    /**
     * @return non-empty-string
     */
    private static function getSchemaNameForThisClassName(): string
    {
        $className = s(new ReflectionClass(self::class)->getShortName());

        if ($className->endsWith('Collection') && !$className->equalsTo('Collection')) {
            $className = $className->beforeLast('Collection');
        }

        $name = $className
            ->snake()
            ->lower()
            ->toString()
        ;

        if ($name === '') {
            throw new UnreachableException();
        }

        return $name;
    }

    /**
     * @throws InvalidPropertyException
     */
    private static function assertAllowedPropertyName(string $name): void
    {
        if (self::isReservedPropertyName($name)) {
            throw new InvalidPropertyException(sprintf(
                'Property name "%s" is reserved and cannot be used.',
                $name,
            ));
        }
    }

    private static function isReservedPropertyName(string $name): bool
    {
        return in_array($name, self::RESERVED_PROPERTY_NAMES, true);
    }
}
