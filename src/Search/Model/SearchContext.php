<?php

declare(strict_types=1);

namespace App\Search\Model;

use App\Search\Collection\CollectionInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchContext
{
    public const int PAGE_SIZE_1   = 1;
    public const int PAGE_SIZE_2   = 2;
    public const int PAGE_SIZE_5   = 5;
    public const int PAGE_SIZE_10  = 10;
    public const int PAGE_SIZE_20  = 20;
    public const int PAGE_SIZE_50  = 50;
    public const int PAGE_SIZE_100 = 100;

    public const array PAGE_SIZES = [
        self::PAGE_SIZE_1,
        self::PAGE_SIZE_2,
        self::PAGE_SIZE_5,
        self::PAGE_SIZE_10,
        self::PAGE_SIZE_20,
        self::PAGE_SIZE_50,
        self::PAGE_SIZE_100,
    ];

    public function __construct(
        #[SerializedName(serializedName: 'c')]
        #[Assert\NotBlank]
        public CollectionInterface $collection,
        #[SerializedName(serializedName: 'q')]
        public string $query = '',
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Choice(
            choices: self::PAGE_SIZES,
            message: 'Choose a valid page size value.',
        )]
        public int $pageSize = 10,
    ) {}
}
