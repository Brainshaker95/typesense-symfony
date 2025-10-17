<?php

declare(strict_types=1);

namespace App\Search\Collection;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\Attribute\Required;
use Traversable;

use function array_values;
use function iterator_to_array;

trait CollectionsTrait
{
    /**
     * @var list<CollectionInterface>
     */
    private array $collections;

    /**
     * @param Traversable<CollectionInterface> $collections
     */
    #[Required]
    public function setCollections(#[AutowireIterator(tag: 'app.search.collection')] Traversable $collections): void
    {
        $this->collections = array_values(iterator_to_array($collections));
    }
}
