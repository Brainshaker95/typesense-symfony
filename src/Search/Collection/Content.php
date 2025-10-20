<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Model\Attribute\Field;
use App\Search\Model\Field as ModelField;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type ArrayRepresentation array{
 *     id: string,
 *     title: string,
 *     content: string,
 *     locale?: string,
 * }
 *
 * @implements CollectionInterface<ArrayRepresentation>
 */
final readonly class Content implements CollectionInterface
{
    /**
     * @use CollectionTrait<ArrayRepresentation>
     */
    use CollectionTrait;

    public function __construct(
        #[Assert\NotBlank]
        #[Field]
        public string $id,
        #[Assert\NotBlank]
        #[Field(query: true, sort: 'asc')]
        public string $title,
        #[Field(query: true, queryPriority: 1, isDefaultSortingField: true, sortPriority: 1)]
        public string $content,
        #[Field(type: ModelField::TYPE_STRING)]
        public ?string $locale = null,
    ) {}
}
