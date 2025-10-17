<?php

declare(strict_types=1);

namespace App\Search\Command;

use App\Search\Collection\CollectionInterface;
use App\Search\Exception\InvalidPropertyException;
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
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    public function __invoke(#[Option] array $collections = [], #[Option] bool $truncate = false): int
    {
        $collections = $this->getValidCollections($collections);

        $this->info('Building search index…');

        foreach ($collections as $collection) {
            $collectionName = $collection::getSchema()->name;

            if ($truncate) {
                try {
                    $this->typesenseService->truncate($collection);
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

                $data      = $repository->getData();
                $isValid   = static fn (CollectionInterface $subject): bool => $subject::getSchema()->name === $collectionName;
                $upserts   = array_values(array_filter($data['upserts'] ?? [], $isValid(...)));
                $deletions = array_values(array_filter($data['deletions'] ?? [], $isValid(...)));

                if ($upserts !== []) {
                    $upsertsCount = count($upserts);

                    $this->info(sprintf(
                        'Indexing %s subject%s for collection "%s"…',
                        $upsertsCount,
                        $upsertsCount === 1 ? '' : 's',
                        $collectionName,
                    ));

                    try {
                        $this->withStopwatch(
                            callback: fn () => $this->typesenseService->index($upserts),
                            onDone: fn (StopwatchPeriod $period) => $this->io->writeln(sprintf(
                                '<fg=magenta>Done (%s)</>',
                                $period,
                            )),
                        );
                    } catch (Throwable $throwable) {
                        $this->error($throwable, sprintf(
                            'Error while indexing collection "%s"',
                            $collectionName,
                        ));

                        return self::FAILURE;
                    }
                }

                if ($deletions !== []) {
                    $deletionsCount = count($deletions);

                    $this->info(sprintf(
                        'Deleting %s subject%s from collection "%s"…',
                        $deletionsCount,
                        $deletionsCount === 1 ? '' : 's',
                        $collectionName,
                    ));

                    try {
                        $this->withStopwatch(
                            callback: fn () => $this->typesenseService->deleteDocuments($deletions, 'log'),
                            onDone: fn (StopwatchPeriod $period) => $this->io->writeln(sprintf(
                                '<fg=magenta>Done (%s)</>',
                                $period,
                            )),
                        );
                    } catch (Throwable $throwable) {
                        $this->error($throwable, sprintf(
                            'Error while deleting from collection "%s"',
                            $collectionName,
                        ));

                        return self::FAILURE;
                    }
                }
            }
        }

        $this->success('Search index built');

        return self::SUCCESS;
    }

    #[Override]
    protected function configure(): void
    {
        $this->traitConfigure();

        $this
            ->addOption(
                name: 'truncate',
                description: 'Truncate the given collection(s) before indexing',
                shortcut: 't',
                mode: InputOption::VALUE_NONE,
            )
        ;
    }
}
