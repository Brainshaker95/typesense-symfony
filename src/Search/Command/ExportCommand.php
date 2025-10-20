<?php

declare(strict_types=1);

namespace App\Search\Command;

use App\Search\Exception\InvalidSchemaException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Throwable;

use function count;
use function sprintf;

#[AsCommand(
    name: 'app:search:export',
    description: 'Builds the search index for the given collection(s)',
)]
final class ExportCommand extends Command
{
    use CommandTrait;

    /**
     * @param list<mixed> $collections
     *
     * @throws InvalidOptionException
     * @throws InvalidSchemaException
     */
    public function __invoke(#[Option] array $collections = []): int
    {
        $collections     = $this->getValidCollections($collections);
        $collectionCount = count($collections);

        foreach ($collections as $index => $collection) {
            $collectionName = $collection::getSchema()->name;

            $this->info(sprintf(
                'Data for collection "%s"',
                $collectionName,
            ));

            try {
                $this->io->writeln($this->typesenseService->export($collection));
            } catch (Throwable $throwable) {
                $this->error($throwable, sprintf(
                    'Error while exporting collection "%s"',
                    $collectionName,
                ));

                return self::FAILURE;
            }

            if ($index < $collectionCount - 1) {
                $this->io->writeln('');
            }
        }

        return self::SUCCESS;
    }
}
