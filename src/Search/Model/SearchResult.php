<?php

declare(strict_types=1);

namespace App\Search\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @template TItem of mixed = mixed
 */
final readonly class SearchResult
{
    /**
     * @param list<TItem> $items
     * @param int<0, max> $totalCount
     */
    public function __construct(
        public array $items,
        #[Assert\PositiveOrZero]
        public int $totalCount,
    ) {}
}
