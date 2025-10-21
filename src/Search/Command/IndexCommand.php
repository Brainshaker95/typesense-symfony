<?php

declare(strict_types=1);

namespace App\Search\Command;

use App\Search\Collection\CollectionInterface;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Repository\RepositoriesTrait;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Stopwatch\StopwatchPeriod;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function sprintf;

#[AsCommand(
    name: 'app:search:index',
    description: 'Builds the search index for the given collection(s)',
)]
final class IndexCommand extends Command
{
    use CommandTrait {
        CommandTrait::configure as private traitConfigure;
    }
    use RepositoriesTrait;

    /**
     * @param list<mixed> $collections
     *
     * @throws InvalidOptionException
     * @throws InvalidSchemaException
     */
    public function __invoke(
        #[Option]
        array $collections = [],
        #[Option]
        bool $delete = false,
        #[Option]
        bool $truncate = false,
    ): int {
        if ($delete && $truncate) {
            throw new InvalidOptionException('Options "--delete" and "--truncate" cannot be used together.');
        }

        $collections = $this->getValidCollections($collections);

        $this->info('Building search index');

        $this->withStopwatch(
            callback: fn () => $this->indexCollections($collections, $delete, $truncate),
            onDone: fn (StopwatchPeriod $period) => $this->success(sprintf('Search index built (%s)', $period)),
        );

        return self::SUCCESS;
    }

    #[Override]
    protected function configure(): void
    {
        $this->traitConfigure();

        $this
            ->addOption(
                name: 'delete',
                description: 'Delete the given collection(s)',
                shortcut: 'd',
                mode: InputOption::VALUE_NONE,
            )
            ->addOption(
                name: 'truncate',
                description: 'Truncate the given collection(s) before indexing',
                shortcut: 't',
                mode: InputOption::VALUE_NONE,
            )
        ;
    }

    /**
     * @param list<CollectionInterface> $collections
     *
     * @return self::SUCCESS|self::FAILURE
     *
     * @throws InvalidSchemaException
     */
    private function indexCollections(array $collections, bool $doDelete, bool $doTruncate): int
    {
        foreach ($collections as $collection) {
            $resultCode = $this->indexCollection($collection, $doDelete, $doTruncate);

            if ($resultCode !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return self::SUCCESS|self::FAILURE
     *
     * @throws InvalidSchemaException
     */
    private function indexCollection(CollectionInterface $collection, bool $doDelete, bool $doTruncate): int
    {
        $collectionName = $collection::getSchema()->name;

        if ($doDelete) {
            $this->info(sprintf(
                'Deleting collection "%s"',
                $collectionName,
            ));

            try {
                $this->withStopwatch(
                    callback: fn () => $this->typesenseService->delete($collection),
                    onDone: fn (StopwatchPeriod $period) => $this->info(sprintf('Done (%s)', $period)),
                );
            } catch (Throwable $throwable) {
                $this->error($throwable, sprintf(
                    'Error while deleting collection "%s"',
                    $collectionName,
                ));

                return self::FAILURE;
            }
        }

        if ($doTruncate) {
            $this->info(sprintf(
                'Truncating collection "%s"',
                $collectionName,
            ));

            try {
                $this->withStopwatch(
                    callback: fn () => $this->typesenseService->truncate($collection),
                    onDone: fn (StopwatchPeriod $period) => $this->info(sprintf('Done (%s)', $period)),
                );
            } catch (Throwable $throwable) {
                $this->error($throwable, sprintf(
                    'Error while truncating collection "%s"',
                    $collectionName,
                ));

                return self::FAILURE;
            }
        }

        foreach ($this->repositories as $repository) {
            if (!$repository->supports($collection)) {
                continue;
            }

            $this->info(sprintf(
                'Retrieving data for collection "%s" with repository "%s"',
                $collectionName,
                $repository::class,
            ));

            $data = $this->withStopwatch(
                callback: fn () => $repository->getData($this->output),
                onDone: fn (StopwatchPeriod $period) => $this->info(sprintf('Done (%s)', $period)),
            );

            $isValid        = static fn (CollectionInterface $subject): bool => $subject::getSchema()->name === $collectionName;
            $upserts        = array_values(array_filter($data['upserts'] ?? [], $isValid(...)));
            $deletions      = array_values(array_filter($data['deletions'] ?? [], $isValid(...)));
            $upsertsCount   = count($upserts);
            $deletionsCount = count($deletions);

            if ($upsertsCount === 0 && $deletionsCount === 0) {
                $this->note('No data found');
            }

            if ($upsertsCount > 0) {
                $this->info(sprintf(
                    'Indexing %s subject%s for collection "%s"',
                    $upsertsCount,
                    $upsertsCount === 1 ? '' : 's',
                    $collectionName,
                ));

                try {
                    $this->withStopwatch(
                        callback: fn () => $this->typesenseService->index($upserts),
                        onDone: fn (StopwatchPeriod $period) => $this->info(sprintf('Done (%s)', $period)),
                    );
                } catch (Throwable $throwable) {
                    $this->error($throwable, sprintf('Error while indexing collection "%s"', $collectionName));

                    return self::FAILURE;
                }
            }

            if ($deletionsCount > 0) {
                $this->info(sprintf(
                    'Deleting %s subject%s from collection "%s"',
                    $deletionsCount,
                    $deletionsCount === 1 ? '' : 's',
                    $collectionName,
                ));

                try {
                    $this->withStopwatch(
                        callback: fn () => $this->typesenseService->deleteDocuments($deletions, 'log'),
                        onDone: fn (StopwatchPeriod $period) => $this->info(sprintf('Done (%s)', $period)),
                    );
                } catch (Throwable $throwable) {
                    $this->error($throwable, sprintf('Error while deleting from collection "%s"', $collectionName));

                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
