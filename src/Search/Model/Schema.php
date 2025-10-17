<?php

declare(strict_types=1);

namespace App\Search\Model;

use App\Search\Exception\InvalidSchemaException;
use App\Search\Model\Support\ArrayableInterface;
use App\Search\Model\Support\ArrayableTrait;
use Override;

use function array_any;
use function array_key_exists;
use function array_map;
use function array_merge;
use function in_array;
use function sprintf;

final class Schema implements ArrayableInterface
{
    use ArrayableTrait {
        ArrayableTrait::toArray as private defaultToArray;
    }

    /**
     * @param non-empty-string $name
     * @param list<Field> $fields
     * @param ?non-empty-string $default_sorting_field
     * @param ?list<non-empty-string> $token_separators
     * @param ?list<non-empty-string> $symbols_to_index
     * @param ?array<string, mixed> $metadata
     */
    public function __construct(
        public string $name,
        public array $fields {
            get => $this->fields;

            /**
             * @throws InvalidSchemaException
             */
            set(array $fields) {
                $this->validateFields($fields);

                $this->fields = $fields;
            }
        },
        public ?string $default_sorting_field = null,
        public ?array $token_separators = null,
        public ?array $symbols_to_index = null,
        public ?bool $enable_nested_fields = null {
            get => $this->enable_nested_fields ?? null;
            set(?bool $enable_nested_fields) {
                if ($enable_nested_fields !== null) {
                    $this->enable_nested_fields = $enable_nested_fields;

                    return;
                }

                $this->enable_nested_fields = array_any(
                    $this->fields,
                    static fn ($field): bool => in_array($field->type, [Field::TYPE_OBJECT, Field::TYPE_OBJECT_ARRAY], true),
                ) ?: null;
            }
        },
        public ?array $metadata = null {
            get => $this->metadata;
            set(?array $metadata) {
                if ($metadata === null) {
                    $this->metadata = null;

                    return;
                }

                $this->metadata = array_map(
                    static fn (mixed $item): mixed => $item instanceof ArrayableInterface
                        ? $item->toArray()
                        : $item,
                    $metadata,
                );
            }
        },
    ) {}

    #[Override]
    public function toArray(): array
    {
        return array_merge($this->defaultToArray(), [
            'fields' => array_map(
                static fn (Field $field): array => $field->toArray(),
                $this->fields,
            ),
        ]);
    }

    /**
     * @param list<Field> $fields
     *
     * @throws InvalidSchemaException
     */
    private function validateFields(array $fields): void
    {
        $validFields = [];

        foreach ($fields as $field) {
            if (array_key_exists($field->name, $validFields)) {
                throw new InvalidSchemaException(sprintf('Field name "%s" is already used in this schema.', $field->name));
            }

            $validFields[$field->name] = true;
        }
    }
}
