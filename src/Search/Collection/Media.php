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
final class Media implements CollectionInterface
{
    /**
     * @use CollectionTrait<ArrayRepresentation>
     */
    use CollectionTrait;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(options: ['image', 'video'])]
        #[Field]
        public readonly string $type,
        #[Assert\NotBlank]
        #[Field(query: true)]
        public readonly string $title,
        #[Assert\NotBlank]
        #[Field(query: true)]
        public readonly string $author,
        #[Field]
        public readonly ?float $length = null,
        #[Field(query: true)]
        public readonly ?string $description = null,
        #[Field(query: true)]
        public readonly ?string $caption = null,
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
