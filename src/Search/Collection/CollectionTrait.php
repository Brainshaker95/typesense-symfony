<?php

declare(strict_types=1);

namespace App\Search\Collection;

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
use Symfony\Component\TypeInfo\TypeIdentifier;
use Throwable;
use TypeError;

use function array_filter;
use function array_flip;
use function array_intersect_key;
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
        ArrayableTrait::toArray as private traitToArray;
    }

    /**
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
     * @throws InvalidSchemaException
     */
    public static function getSearchParameters(SearchContext $searchContext): array
    {
        $parameters = [
            'query_by' => self::getQueryBy(),
        ];

        $sortBy = self::getSortBy();

        if ($sortBy !== null) {
            $parameters['sort_by'] = $sortBy;
        }

        return $parameters;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function getTypesenseId(): string
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        return property_exists($this, 'id') && is_string($this->id)
            ? $this->id
            : throw new InvalidSchemaException(sprintf(
                'Collection "%s" does not expose a string "id" property and has not overridden the "getTypesenseId" method. Add a string "id" property to the class or implement a custom "getTypesenseId" method.',
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

            if (!self::isFieldName($name)) {
                continue;
            }

            if (array_key_exists($name, $data)) {
                $arguments[$name] = $data[$name];
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

            if (!self::isFieldName($name)) {
                continue;
            }

            if (array_key_exists($name, $data)) {
                $property->setValue($instance, $data[$name]);
            } elseif ($property->hasDefaultValue()) {
                $property->setValue($instance, $property->getDefaultValue());
            } elseif ($property->getType()?->allowsNull() ?? false) {
                $property->setValue($instance, null);
            }
        }

        return $instance;
    }

    /**
     * @throws InvalidSchemaException
     */
    #[Override]
    public function toArray(): array
    {
        /**
         * @var TArrayRepresentation $array
         */
        $array = array_intersect_key($this->traitToArray(), array_flip(self::getFieldNames()));

        return array_merge($array, [
            'id' => $this->getTypesenseId(),
        ]);
    }

    /**
     * @return list<Field>
     *
     * @throws InvalidSchemaException
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
     * @return non-empty-string
     *
     * @throws InvalidSchemaException
     */
    private static function getQueryBy(): string
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

        if (in_array('id', $queryBy, true)) {
            throw new InvalidSchemaException(sprintf(
                'Collection "%s" defines the "id" property as queryable; the "id" field cannot be used in "query_by".',
                self::class,
            ));
        }

        return implode(',', $queryBy) ?: throw new InvalidSchemaException(sprintf(
            'Collection "%s" does not define any queryable fields.',
            self::class,
        ));
    }

    /**
     * @return ?non-empty-string
     *
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
                    'Collection "%s" defines to many sortable fields; only 3 are allowed. Property "%s" would be the fourth sortable field.',
                    self::class,
                    $attribute['property']->getName(),
                ));
            }
        }

        $defaultSortingField = self::getDefaultSortingField();

        if ($count < 3) {
            $sortBy = ['_text_match:desc', ...$sortBy];
            $count += 1;
        }

        if ($count < 3 && $defaultSortingField !== null) {
            $sortBy[] = $defaultSortingField . ':desc';
            $count += 1;
        }

        return implode(',', $sortBy) ?: null;
    }

    /**
     * @return ?non-empty-string
     *
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
                        'Collection "%s" defines more than one default sorting field; only one is allowed.',
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
     * @throws InvalidSchemaException
     */
    private static function getFieldAttributes(): array
    {
        $class      = new ReflectionClass(self::class);
        $properties = $class->getProperties();
        $results    = [];

        foreach ($properties as $property) {
            $fieldAttributes = $property->getAttributes(AttributeField::class);

            if (!isset($fieldAttributes[0])) {
                continue;
            }

            if (count($fieldAttributes) > 1) {
                throw new InvalidSchemaException(sprintf(
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
        } elseif ($typeName->startsWith('?')) {
            $typeName = $typeName->trimStart('?');
        }

        $typeMap  = self::getTypeMap();
        $typeName = $typeName->toString();

        if (array_key_exists($typeName, $typeMap)) {
            return $typeMap[$typeName];
        }

        // @phpstan-ignore-next-line symplify.forbiddenFuncCall
        if (class_exists($typeName) || interface_exists($typeName) || enum_exists($typeName)) {
            return Field::TYPE_OBJECT;
        }

        $typeName = s($typeName);

        if ($typeName->startsWith('int<')) {
            return Field::TYPE_INT64;
        }

        if ($typeName->startsWith('array{')) {
            return $typeName->endsWith('[]')
                ? Field::TYPE_OBJECT_ARRAY
                : Field::TYPE_OBJECT;
        }

        if (!$typeName->endsWith('[]')
            && !$typeName->startsWith('array<')
            && !$typeName->startsWith('list<')
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
            && in_array($typeName->toString(), ['array-key', 'int', 'positive-int', 'non-negative-int'], true)) {
            $typeName = $firstMatch->trimEnd('>')->afterLast(',')->trim();

            if ($typeName->endsWith('[]')) {
                return Field::TYPE_OBJECT;
            }
        }

        return $typeMap[$typeName->trimEnd('[]')->toString()] ?? Field::TYPE_OBJECT_ARRAY;
    }

    private static function getTypeNameFromDocComment(ReflectionProperty $property): AbstractString
    {
        $docComment    = s($property->getDocComment() ?: '');
        $varTagMatches = $docComment->match('/@var\s+([^\s]+)/m');

        if ($varTagMatches !== []) {
            return s(is_string($varTagMatches[1] ?? null) ? $varTagMatches[1] : '')->trim();
        }

        if ($property->isPromoted()) {
            $docComment = s($property->getDeclaringClass()->getConstructor()?->getDocComment() ?: $docComment->toString());
            $matches    = $docComment->match(sprintf('/@param\s+([^\s]+)\s+\$%s\b/m', preg_quote($property->getName(), '/')));

            if (is_string($matches[1] ?? null)) {
                return s($matches[1])->trim();
            }
        }

        return s();
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
     * @throws InvalidSchemaException
     *
     * @phpstan-assert-if-true key-of<TArrayRepresentation> $name
     */
    private static function isFieldName(string $name): bool
    {
        return in_array($name, self::getFieldNames(), true);
    }

    /**
     * @param key-of<TArrayRepresentation> $name
     *
     * @throws InvalidSchemaException
     */
    private static function getAndAssertFieldName(string $name): string
    {
        // @phpstan-ignore-next-line staticMethod.alreadyNarrowedType
        if (!self::isFieldName($name)) {
            throw new InvalidSchemaException(sprintf(
                'Field with name "%s" does not exist in the schema. Fields are: ["%s"]',
                $name,
                implode('", "', self::getFieldNames()),
            ));
        }

        return $name;
    }

    /**
     * @return list<key-of<TArrayRepresentation>>
     *
     * @throws InvalidSchemaException
     */
    private static function getFieldNames(): array
    {
        /**
         * @var list<key-of<TArrayRepresentation>> $fieldNames
         */
        $fieldNames = array_map(
            static fn (Field $field): string => $field->name,
            self::getSchema()->fields,
        );

        return $fieldNames;
    }

    /**
     * @return array<string, Field::TYPE_*>
     */
    private static function getTypeMap(): array
    {
        return [
            TypeIdentifier::INT->value    => Field::TYPE_INT64,
            TypeIdentifier::FLOAT->value  => Field::TYPE_FLOAT,
            TypeIdentifier::OBJECT->value => Field::TYPE_OBJECT,
            TypeIdentifier::BOOL->value   => Field::TYPE_BOOL,
            TypeIdentifier::TRUE->value   => Field::TYPE_BOOL,
            TypeIdentifier::FALSE->value  => Field::TYPE_BOOL,
            TypeIdentifier::STRING->value => Field::TYPE_STRING,
            'positive-int'                => Field::TYPE_INT64,
            'non-positive-int'            => Field::TYPE_INT64,
            'negative-int'                => Field::TYPE_INT64,
            'non-negative-int'            => Field::TYPE_INT64,
            'non-zero-int'                => Field::TYPE_INT64,
            'non-empty-string'            => Field::TYPE_STRING,
            'callable-string'             => Field::TYPE_STRING,
            'numeric-string'              => Field::TYPE_STRING,
            'non-falsy-string'            => Field::TYPE_STRING,
            'literal-string'              => Field::TYPE_STRING,
            'lowercase-string'            => Field::TYPE_STRING,
        ];
    }
}
