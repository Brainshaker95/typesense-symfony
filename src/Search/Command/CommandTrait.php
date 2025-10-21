<?php

declare(strict_types=1);

namespace App\Search\Command;

use App\Search\Collection\CollectionInterface;
use App\Search\Collection\CollectionsTrait;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Exception\UnreachableException;
use App\Search\Typesense\TypesenseService;
use Closure;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchPeriod;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_scalar;
use function is_string;
use function sprintf;
use function uniqid;

use const PHP_EOL;

trait CommandTrait
{
    use CollectionsTrait;

    private const string PSEUDO_COLLECTION_ALL = 'all';

    private InputInterface $input;

    private OutputInterface $output;

    private SymfonyStyle $io;

    public function __construct(
        private readonly TypesenseService $typesenseService,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input  = $input;
        $this->output = $output;
        $this->io     = new SymfonyStyle($this->input, $this->output);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'collections',
                description: 'The collection(s) to index',
                shortcut: 'c',
                mode: InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                default: [self::PSEUDO_COLLECTION_ALL],
                suggestedValues: $this->getCollectionNames(...),
            )
        ;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidSchemaException
     */
    private function getCollectionNames(): array
    {
        return [self::PSEUDO_COLLECTION_ALL, ...array_map(
            static fn (CollectionInterface $collection): string => $collection::getSchema()->name,
            $this->collections,
        )];
    }

    /**
     * @param list<mixed> $collections
     *
     * @return list<CollectionInterface>
     *
     * @throws InvalidOptionException
     * @throws InvalidSchemaException
     */
    private function getValidCollections(array $collections): array
    {
        $collectionNames = $this->getCollectionNames();

        foreach ($collections as $collectionName) {
            if (!in_array($collectionName, $collectionNames, true)) {
                throw new InvalidOptionException(sprintf(
                    'The collection "%s" is not valid. Valid collections are: "%s".',
                    is_scalar($collectionName) ? $collectionName : 'unknown',
                    implode('", "', $collectionNames),
                ));
            }
        }

        if (in_array(self::PSEUDO_COLLECTION_ALL, $collections, true)) {
            if (count($collections) > 1) {
                throw new InvalidOptionException(sprintf(
                    'The collection "%s" cannot be combined with other collections.',
                    self::PSEUDO_COLLECTION_ALL,
                ));
            }

            return $this->collections;
        }

        return array_values(array_filter(
            $this->collections,
            static fn (CollectionInterface $collection): bool => in_array(
                $collection::getSchema()->name,
                $collections,
                true,
            ),
        ));
    }

    /**
     * @param Closure(): void $callback
     * @param Closure(StopwatchPeriod $period): void $onDone
     */
    private function withStopwatch(Closure $callback, Closure $onDone): void
    {
        $stopwatch = new Stopwatch();
        $name      = uniqid($this->getName() . '_stopwatch_');

        $stopwatch->start($name);
        $callback();
        $stopwatch->stop($name);

        $periods = $stopwatch->getEvent($name)->getPeriods();
        $period  = $periods[0] ?? null;

        if ($period === null) {
            throw new UnreachableException('Expected at least one period.');
        }

        $onDone($period);
    }

    private function info(string $message): void
    {
        $this->io->writeln(sprintf('<fg=magenta>%s</>', $message));
    }

    private function success(string $message): void
    {
        $this->io->writeln(sprintf('<fg=green>%s</>', $message));
    }

    private function error(string|Throwable $messageOrThrowable, ?string $messagePrefix = null): void
    {
        $message = match (true) {
            is_string($messageOrThrowable) => $messageOrThrowable,
            $this->io->isVerbose()         => sprintf(
                '%s (Code: %s): %s%sThrown at %s%s',
                $messageOrThrowable::class,
                $messageOrThrowable->getCode(),
                $messageOrThrowable->getMessage(),
                PHP_EOL,
                $messageOrThrowable->getFile() . ':' . $messageOrThrowable->getLine(),
                $this->io->isVeryVerbose() ? (PHP_EOL . $messageOrThrowable->getTraceAsString()) : '',
            ),
            default => $messageOrThrowable->getMessage() . ' <comment>(Retry with -v or -vv for more details)</comment>',
        };

        $this->io->writeln(sprintf(
            '<fg=red>%s%s</>',
            is_string($messagePrefix) ? $messagePrefix . ' – ' : '',
            $message,
        ));
    }
}
