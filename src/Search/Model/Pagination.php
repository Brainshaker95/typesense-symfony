<?php

declare(strict_types=1);

namespace App\Search\Model;

use App\Search\Model\Support\ArrayableInterface;
use App\Search\Model\Support\ArrayableTrait;

use function ceil;
use function max;
use function min;
use function Symfony\Component\String\s;

/**
 * @template TItem of mixed = mixed
 *
 * @phpstan-type TemplateVariables array{
 *     page: int,
 *     page_size: int,
 *     total_count: int,
 *     padding: int,
 *     page_count: int,
 *     first_page: int,
 *     last_page: int,
 *     previous_page: int,
 *     next_page: int,
 *     visible_page_count: int,
 *     is_in_first_block: bool,
 *     is_in_last_block: bool,
 *     lower_bound: int,
 *     upper_bound: int,
 *     is_visible: bool,
 * }
 */
final class Pagination implements ArrayableInterface
{
    /**
     * @use ArrayableTrait<array{
     *     page: int,
     *     pageSize: int,
     *     totalCount: int,
     *     padding: int,
     *     pageCount: int,
     *     firstPage: int,
     *     lastPage: int,
     *     previousPage: int,
     *     nextPage: int,
     *     visiblePageCount: int,
     *     isInFirstBlock: bool,
     *     isInLastBlock: bool,
     *     lowerBound: int,
     *     upperBound: int,
     *     isVisible: bool,
     * }>
     */
    use ArrayableTrait;

    /**
     * @param int<1, max> $page
     * @param int<1, 250> $pageSize
     * @param int<0, max> $totalCount
     * @param int<0, max> $padding
     * @param int<1, max> $pageCount
     * @param 1 $firstPage
     * @param int<0, max> $lastPage
     */
    private function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $totalCount,
        public readonly int $padding = 2,
        public private(set) ?int $pageCount = null {
            get {
                $this->pageCount ??= max(1, (int) ceil($this->totalCount / $this->pageSize));

                return $this->pageCount;
            }
        },
        public private(set) ?int $firstPage = null {
            get {
                $this->firstPage ??= 1;

                return $this->firstPage;
            }
        },
        public private(set) ?int $lastPage = null {
            get {
                $this->lastPage ??= $this->pageCount;

                return $this->lastPage;
            }
        },
        public private(set) ?int $previousPage = null {
            get {
                $this->previousPage ??= max(1, $this->page - 1);

                return $this->previousPage;
            }
        },
        public private(set) ?int $nextPage = null {
            get {
                $this->nextPage ??= min($this->pageCount, $this->page + 1);

                return $this->nextPage;
            }
        },
        public private(set) ?int $visiblePageCount = null {
            get {
                $this->visiblePageCount ??= 2 * $this->padding + 1;

                return $this->visiblePageCount;
            }
        },
        public private(set) ?bool $isInFirstBlock = null {
            get {
                $this->isInFirstBlock ??= $this->page - $this->padding <= 0;

                return $this->isInFirstBlock;
            }
        },
        public private(set) ?bool $isInLastBlock = null {
            get {
                $this->isInLastBlock ??= $this->page + $this->padding >= $this->pageCount;

                return $this->isInLastBlock;
            }
        },
        public private(set) ?int $lowerBound = null {
            get {
                if ($this->lowerBound === null) {
                    if (!($this->page - $this->padding <= 0)) {
                        if (!($this->page + $this->padding >= $this->pageCount)) {
                            $this->lowerBound = max(1, $this->page - $this->padding);
                        } else {
                            $this->lowerBound = max(1, ($this->pageCount ?? 1) - ($this->visiblePageCount ?? 1) + 1);
                        }
                    } else {
                        $this->lowerBound = 1;
                    }
                }

                return $this->lowerBound;
            }
        },
        public private(set) ?int $upperBound = null {
            get {
                if ($this->upperBound === null) {
                    if (!($this->page - $this->padding <= 0)) {
                        if (!($this->page + $this->padding >= $this->pageCount)) {
                            $this->upperBound = min($this->pageCount, $this->page + $this->padding);
                        } else {
                            $this->upperBound = $this->pageCount;
                        }
                    } else {
                        $this->upperBound = min($this->pageCount, $this->visiblePageCount);
                    }
                }

                return $this->upperBound;
            }
        },
        public private(set) ?bool $isVisible = null {
            get {
                if ($this->isVisible === null) {
                    $this->isVisible = $this->pageCount > 1;
                }

                return $this->isVisible;
            }
        },
    ) {}

    /**
     * @param int<0, max> $totalCount
     * @param int<1, max> $page
     * @param int<1, 250> $pageSize
     * @param int<0, max> $padding
     */
    public static function fromTotalCount(int $totalCount, int $page, int $pageSize, int $padding = 2): self
    {
        return new self($page, $pageSize, $totalCount, $padding);
    }

    public static function fromSearch(SearchContext $searchContext, SearchResult $searchResult): self
    {
        return self::fromTotalCount(
            totalCount: $searchResult->totalCount,
            page: $searchContext->page,
            pageSize: $searchContext->pageSize,
        );
    }

    public function isPageVisible(int $index): bool
    {
        return $index >= 1
            && ($this->isVisible ?? false)
            && $index <= $this->pageCount
            && ($this->pageCount <= $this->visiblePageCount
                || ($index >= $this->lowerBound && $index <= $this->upperBound));
    }

    /**
     * @return TemplateVariables
     */
    public function toTemplateVariables(): array
    {
        $data      = $this->toArray();
        $variables = [];

        foreach ($data as $key => $value) {
            $variables[s($key)->snake()->toString()] = $value;
        }

        return $variables;
    }
}
