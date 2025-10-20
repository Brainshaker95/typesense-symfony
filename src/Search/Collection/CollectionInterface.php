<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Exception\InvalidPropertyException;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Model\Schema;
use App\Search\Model\SearchContext;
use App\Search\Model\Support\ArrayableInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * @template TArrayRepresentation of array<non-empty-string, mixed> = array<non-empty-string, mixed>
 */
#[Autoconfigure(
    tags: ['app.search.collection'],
    autowire: false,
    lazy: true,
)]
interface CollectionInterface extends ArrayableInterface
{
    /**
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    public static function getSchema(): Schema;

    /**
     * @return array<string, string>
     */
    public static function getSearchParameters(SearchContext $searchContext): array;

    /**
     * @param TArrayRepresentation $data
     */
    public static function fromArray(array $data): self;

    /**
     * @return TArrayRepresentation
     */
    #[Override]
    public function toArray(): array;

    public function getTypesenseId(): string;
}
