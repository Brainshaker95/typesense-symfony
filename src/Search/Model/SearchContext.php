<?php

declare(strict_types=1);

namespace App\Search\Model;

use App\Search\Collection\CollectionInterface;
use App\Search\Exception\CollectionNotFoundException;
use App\Search\Exception\InvalidSchemaException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_find;
use function count;
use function in_array;
use function max;
use function min;
use function reset;

final class SearchContext
{
    public const string QUERY_DEFAULT  = '';
    public const int PAGE_DEFAULT      = 1;
    public const int PAGE_SIZE_DEFAULT = 10;

    public const int PAGE_SIZE_1   = 1;
    public const int PAGE_SIZE_2   = 2;
    public const int PAGE_SIZE_5   = 5;
    public const int PAGE_SIZE_10  = 10;
    public const int PAGE_SIZE_20  = 20;
    public const int PAGE_SIZE_50  = 50;
    public const int PAGE_SIZE_100 = 100;
    // NOTICE: 250 is the maximum number of hits per page typesense allows
    public const int PAGE_SIZE_MAX = 250;

    public const array PAGE_SIZES = [
        self::PAGE_SIZE_1,
        self::PAGE_SIZE_2,
        self::PAGE_SIZE_5,
        self::PAGE_SIZE_10,
        self::PAGE_SIZE_20,
        self::PAGE_SIZE_50,
        self::PAGE_SIZE_100,
    ];

    public const string SERIALIZED_NAME_COLLECTION = 'c';
    public const string SERIALIZED_NAME_QUERY      = 'q';
    public const string SERIALIZED_NAME_PAGE       = 'p';
    public const string SERIALIZED_NAME_PAGE_SIZE  = 's';

    /**
     * @param int<1, max> $page
     * @param self::PAGE_SIZE_* $pageSize
     */
    public function __construct(
        #[SerializedName(serializedName: 'c')]
        public CollectionInterface $collection,
        #[SerializedName(serializedName: self::SERIALIZED_NAME_QUERY)]
        public string $query = self::QUERY_DEFAULT,
        #[Assert\Positive]
        #[SerializedName(serializedName: self::SERIALIZED_NAME_PAGE)]
        public int $page = self::PAGE_DEFAULT,
        #[Assert\GreaterThanOrEqual(value: 1)]
        #[Assert\LessThanOrEqual(value: self::PAGE_SIZE_MAX)]
        #[Assert\Choice(
            choices: self::PAGE_SIZES,
            message: 'Choose a valid page size value.',
        )]
        #[SerializedName(serializedName: self::SERIALIZED_NAME_PAGE_SIZE)]
        public int $pageSize = self::PAGE_SIZE_DEFAULT,
    ) {}

    /**
     * @param InputBag<string> $bag
     * @param list<CollectionInterface> $collections
     *
     * @throws CollectionNotFoundException
     * @throws InvalidSchemaException
     * @throws ValidationFailedException
     */
    public static function fromParameterBag(InputBag $bag, array $collections, ?ValidatorInterface $validator = null): self
    {
        try {
            $page = $bag->getInt(self::SERIALIZED_NAME_PAGE, self::PAGE_DEFAULT);
        } catch (UnexpectedValueException) {
            $page = self::PAGE_DEFAULT;
        }

        try {
            $pageSize = $bag->getInt(self::SERIALIZED_NAME_PAGE_SIZE, self::PAGE_SIZE_DEFAULT);
        } catch (UnexpectedValueException) {
            $pageSize = self::PAGE_SIZE_DEFAULT;
        }

        $pageSize = max(1, min($pageSize, self::PAGE_SIZE_MAX));

        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            $pageSize = self::PAGE_SIZE_DEFAULT;
        }

        try {
            $queryString = $bag->getString(self::SERIALIZED_NAME_QUERY, self::QUERY_DEFAULT);
        } catch (BadRequestException) {
            $queryString = self::QUERY_DEFAULT;
        }

        $firstCollection     = reset($collections) ?: null;
        $firstCollectionName = $firstCollection instanceof CollectionInterface ? $firstCollection::getSchema()->name : '';

        try {
            $collectionName = $bag->getString(self::SERIALIZED_NAME_COLLECTION, $firstCollectionName);
        } catch (BadRequestException) {
            $collectionName = $firstCollectionName;
        }

        $collection = array_find(
            $collections,
            static fn (CollectionInterface $collection): bool => $collection::getSchema()->name === $collectionName,
        );

        if (!$collection instanceof CollectionInterface) {
            $collection = $firstCollection;

            if (!$collection instanceof CollectionInterface) {
                throw new CollectionNotFoundException('No collection found.');
            }
        }

        $instance = new self(
            collection: $collection,
            query: $queryString,
            page: $page > 0 ? $page : self::PAGE_DEFAULT,
            pageSize: $pageSize,
        );

        $violations = $validator?->validate($instance) ?? [];

        return count($violations) === 0
            ? $instance
            : throw new ValidationFailedException($instance, $violations);
    }
}
