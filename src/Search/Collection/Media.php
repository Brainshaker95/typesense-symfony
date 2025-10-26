<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Model\Attribute\Field;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\String\s;

/**
 * @phpstan-type ArrayRepresentation array{
 *     type: 'image'|'video',
 *     title: string,
 *     author: string,
 *     length: float,
 *     description?: string,
 *     caption?: string,
 * }
 *
 * @implements CollectionInterface<ArrayRepresentation>
 */
final readonly class Media implements CollectionInterface
{
    /**
     * @use CollectionTrait<ArrayRepresentation>
     */
    use CollectionTrait;

    public function __construct(
        #[Field]
        #[Assert\Choice(options: ['image', 'video'])]
        #[Assert\NotBlank]
        public string $type,
        #[Field(query: true)]
        #[Assert\NotBlank]
        public string $title,
        #[Field(query: true)]
        #[Assert\NotBlank]
        public string $author,
        #[Field]
        public ?float $length = null,
        #[Field(query: true)]
        public ?string $description = null,
        #[Field(query: true)]
        public ?string $caption = null,
    ) {}

    #[Override]
    public function getTypesenseId(): string
    {
        return s($this->type . '_' . $this->title)
            ->replaceMatches('/[ :&]/', '_')
            ->toString()
        ;
    }
}
