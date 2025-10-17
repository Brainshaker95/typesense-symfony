<?php

declare(strict_types=1);

namespace App\Search\Repository;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\Attribute\Required;
use Traversable;

use function array_values;
use function iterator_to_array;

trait RepositoriesTrait
{
    /**
     * @var list<RepositoryInterface>
     */
    private array $repositories;

    /**
     * @param Traversable<RepositoryInterface> $repositories
     */
    #[Required]
    public function setRepositories(#[AutowireIterator(tag: 'app.search.repository')] Traversable $repositories): void
    {
        $this->repositories = array_values(iterator_to_array($repositories));
    }
}
