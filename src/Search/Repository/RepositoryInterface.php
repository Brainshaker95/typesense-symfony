<?php

declare(strict_types=1);

namespace App\Search\Repository;

use App\Search\Collection\CollectionInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template TCollection of CollectionInterface = CollectionInterface
 * @template TTransformed of mixed = mixed
 */
#[AutoconfigureTag(name: 'app.search.repository')]
interface RepositoryInterface
{
    public function supports(CollectionInterface $collection): bool;

    /**
     * @return array{
     *     upserts?: list<TCollection>,
     *     deletions?: list<TCollection>,
     * }
     */
    public function getData(?OutputInterface $output = null): array;

    /**
     * @param TCollection $subject
     * @param array<array-key, mixed> $hit
     *
     * @return ?TTransformed
     */
    public function transform(CollectionInterface $subject, array $hit): mixed;
}
