<?php

declare(strict_types=1);

namespace App\Search\Typesense;

use App\Search\Collection\CollectionInterface;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Model\SearchContext;
use App\Search\Model\SearchResult;
use App\Search\Repository\RepositoriesTrait;
use Http\Client\Exception as HttpClientException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Typesense\Client;
use Typesense\Collection as TypesenseCollection;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function sprintf;
use function urlencode;

final class TypesenseService
{
    use RepositoriesTrait;

    public function __construct(
        private readonly TypesenseConfig $typesenseConfig,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws TypesenseClientError
     */
    public function search(SearchContext $searchContext): SearchResult
    {
        $collection          = $searchContext->collection;
        $typesenseCollection = $this->getOrCreateTypesenseCollection($collection);

        $result = $typesenseCollection->documents->search([
            'q'        => $searchContext->query,
            'page'     => $searchContext->page,
            'per_page' => $searchContext->pageSize,
            ...$collection::getSearchParameters($searchContext),
        ]);

        $hits       = array_key_exists('hits', $result) && is_array($result['hits']) ? array_values($result['hits']) : [];
        $totalCount = array_key_exists('found', $result) && is_int($result['found']) && $result['found'] >= 0 ? $result['found'] : 0;
        $items      = [];

        foreach ($this->repositories as $repository) {
            if (!$repository->supports($collection)) {
                continue;
            }

            foreach ($hits as $hit) {
                $hit      = is_array($hit) ? $hit : [];
                $document = array_key_exists('document', $hit) ? $hit['document'] : [];

                /**
                 * @var array<non-empty-string, mixed> $document
                 */
                $document = is_array($document) ? $document : [];
                $item     = $repository->transform($collection::fromArray($document), $hit);

                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return new SearchResult(
            items: $items,
            totalCount: $totalCount,
        );
    }

    /**
     * @template TSubjects of CollectionInterface|list<CollectionInterface>
     *
     * @param TSubjects $subjects
     *
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws JsonException
     * @throws TypesenseClientError
     * @throws ValidationFailedException
     */
    public function index(CollectionInterface|array $subjects): void
    {
        if (!is_array($subjects)) {
            $subjects = [$subjects];
        }

        $subjectsByCollection = [];

        foreach ($subjects as $subject) {
            $violations = $this->validator->validate($subject);

            if (count($violations) > 0) {
                throw new ValidationFailedException($subject, $violations);
            }

            $subjectsByCollection[$subject::getSchema()->name][] = $subject;
        }

        foreach ($subjectsByCollection as $subjectsForCollection) {
            $mappedSubjects = array_map(
                static fn (CollectionInterface $subject): array => $subject->toArray(),
                $subjectsForCollection,
            );

            $typesenseCollection = $this->getOrCreateTypesenseCollection($subjectsForCollection[0]);

            $typesenseCollection->documents->import($mappedSubjects, [
                'action' => 'upsert',
            ]);
        }
    }

    /**
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws JsonException
     * @throws TypesenseClientError
     */
    public function truncate(CollectionInterface $collection): void
    {
        $typesenseCollection = $this->getOrCreateTypesenseCollection($collection);

        $typesenseCollection->documents->delete([
            'truncate' => true,
        ]);
    }

    /**
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws TypesenseClientError
     */
    public function delete(CollectionInterface $collection): void
    {
        $typesenseCollection = $this->getTypesenseCollection($collection);

        if ($typesenseCollection->exists() ?? false) {
            $typesenseCollection->delete();
        }
    }

    /**
     * @template TSubjects of CollectionInterface|list<CollectionInterface>
     * @template TBehaviorOnNotFound of 'none'|'throw'|'log'
     *
     * @param TSubjects $subjects
     * @param TBehaviorOnNotFound $behaviorOnNotFound
     *
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws JsonException
     * @throws TypesenseClientError
     * @throws ValidationFailedException
     */
    public function deleteDocuments(CollectionInterface|array $subjects, string $behaviorOnNotFound = 'throw'): void
    {
        if (!is_array($subjects)) {
            $subjects = [$subjects];
        }

        foreach ($subjects as $subject) {
            $typesenseCollection = $this->getOrCreateTypesenseCollection($subject);
            $id                  = $subject->getTypesenseId();

            $this->validateId($id);

            try {
                $typesenseCollection->documents->offsetGet($id)->delete();
            } catch (ObjectNotFound $exception) {
                if ($behaviorOnNotFound === 'throw') {
                    throw $exception;
                }

                if ($behaviorOnNotFound === 'log') {
                    $this->logger->error($exception->getMessage(), [
                        'exception' => $exception,
                    ]);
                }
            }
        }
    }

    /**
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws TypesenseClientError
     */
    public function export(CollectionInterface $collection): string
    {
        $typesenseCollection = $this->getTypesenseCollection($collection);

        return $typesenseCollection->documents->export();
    }

    /**
     * @throws InvalidSchemaException
     */
    private function validateId(string $id): void
    {
        if ($id !== urlencode($id)) {
            throw new InvalidSchemaException(sprintf(
                'The provided id "%s" should not be needed to be url-encoded.',
                $id,
            ));
        }
    }

    /**
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws TypesenseClientError
     */
    private function getOrCreateTypesenseCollection(CollectionInterface $collection): TypesenseCollection
    {
        $typesenseCollection = $this->getTypesenseCollection($collection);

        if (!($typesenseCollection->exists() ?? false)) {
            $this->getTypesenseClient()->collections->create($collection::getSchema()->toArray());
            $typesenseCollection->setExists(true);
        }

        return $typesenseCollection;
    }

    /**
     * @throws ConfigError
     * @throws InvalidSchemaException
     */
    private function getTypesenseCollection(CollectionInterface $collection): TypesenseCollection
    {
        return $this->getTypesenseClient()->collections->offsetGet($collection::getSchema()->name);
    }

    /**
     * @throws ConfigError
     */
    private function getTypesenseClient(): Client
    {
        return new Client([
            'api_key' => $this->typesenseConfig->apiKey,
            'nodes'   => [[
                'host'     => $this->typesenseConfig->host,
                'port'     => $this->typesenseConfig->port,
                'path'     => $this->typesenseConfig->path,
                'protocol' => $this->typesenseConfig->scheme,
            ]],
        ]);
    }
}
