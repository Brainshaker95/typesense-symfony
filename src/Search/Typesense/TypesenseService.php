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
use function implode;
use function is_array;
use function is_int;
use function sprintf;
use function Symfony\Component\String\s;
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
     * @throws ValidationFailedException
     */
    public function search(SearchContext $searchContext, bool $doValidateSubjects = true): SearchResult
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
        $items      = $this->hydrateAndTransformHits($collection, $hits, $doValidateSubjects);

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
        $subjects             = is_array($subjects) ? $subjects : [$subjects];
        $subjectsByCollection = $this->getSubjectsByCollection($subjects);

        foreach ($subjectsByCollection as $subjectsForCollection) {
            $dataToImport = array_map(
                static fn (CollectionInterface $subject): array => $subject->toArray(),
                $subjectsForCollection,
            );

            $typesenseCollection = $this->getOrCreateTypesenseCollection($subjectsForCollection[0]);

            $typesenseCollection->documents->import($dataToImport, [
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
     * @throws TypesenseClientError
     * @throws ValidationFailedException
     */
    public function deleteDocuments(CollectionInterface|array $subjects, string $behaviorOnNotFound = 'throw'): void
    {
        $subjects      = is_array($subjects) ? $subjects : [$subjects];
        $singleSubject = count($subjects) === 1 ? $subjects[0] : null;

        if ($singleSubject instanceof CollectionInterface) {
            $this->deleteDocument($singleSubject, $behaviorOnNotFound);

            return;
        }

        $subjectsByCollection = $this->getSubjectsByCollection($subjects, doValidate: false);

        foreach ($subjectsByCollection as $subjectsForCollection) {
            $subjectIds = array_map(
                fn (CollectionInterface $subject): string => $this->getValidatedId($subject),
                $subjectsForCollection,
            );

            $typesenseCollection = $this->getOrCreateTypesenseCollection($subjectsForCollection[0]);

            $response = $typesenseCollection->documents->delete([
                'filter_by' => 'id:[' . implode(',', self::escapeAll($subjectIds)) . ']',
            ]);

            if (($response['num_deleted'] ?? 0) === 0) {
                try {
                    throw new ObjectNotFound(sprintf('Could not find any document for given IDs: %s', implode(', ', $subjectIds)));
                } catch (ObjectNotFound $exception) {
                    $this->handleObjectNotFound($exception, $behaviorOnNotFound);
                }
            }
        }
    }

    /**
     * @template TSubject of CollectionInterface
     * @template TBehaviorOnNotFound of 'none'|'throw'|'log'
     *
     * @param TSubject $subject
     * @param TBehaviorOnNotFound $behaviorOnNotFound
     *
     * @throws ConfigError
     * @throws HttpClientException
     * @throws InvalidSchemaException
     * @throws TypesenseClientError
     */
    public function deleteDocument(CollectionInterface $subject, string $behaviorOnNotFound = 'throw'): void
    {
        $subjectId           = $this->getValidatedId($subject);
        $typesenseCollection = $this->getOrCreateTypesenseCollection($subject);

        try {
            $typesenseCollection->documents->offsetGet($subjectId)->delete();
        } catch (ObjectNotFound $exception) {
            $this->handleObjectNotFound($exception, $behaviorOnNotFound);
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

    public static function escape(string $value): string
    {
        return '`' . s($value)->replaceMatches('/`/', '``')->toString() . '`';
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    public static function escapeAll(array $values): array
    {
        return array_map(self::escape(...), $values);
    }

    /**
     * @param list<mixed> $hits
     *
     * @return list<mixed>
     *
     * @throws ValidationFailedException
     */
    private function hydrateAndTransformHits(CollectionInterface $collection, array $hits, bool $doValidateSubjects = true): array
    {
        $items = [];

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
                $subject  = $collection::fromArray($document);

                if ($doValidateSubjects) {
                    $this->validateSubject($subject);
                }

                $item = $repository->transform($subject, $hit);

                if ($item !== null) {
                    if ($doValidateSubjects) {
                        $this->validateSubject($subject);
                    }

                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param list<CollectionInterface> $subjects
     *
     * @return array<non-empty-string, non-empty-list<CollectionInterface>> $subjectsByCollection
     *
     * @throws InvalidSchemaException
     * @throws ValidationFailedException
     */
    private function getSubjectsByCollection(array $subjects, bool $doValidate = true): array
    {
        $subjectsByCollection = [];

        foreach ($subjects as $subject) {
            if ($doValidate) {
                $this->validateSubject($subject);
            }

            $subjectsByCollection[$subject::getSchema()->name][] = $subject;
        }

        return $subjectsByCollection;
    }

    /**
     * @throws ValidationFailedException
     */
    private function validateSubject(CollectionInterface $subject): void
    {
        $violations = $this->validator->validate($subject);

        if (count($violations) > 0) {
            throw new ValidationFailedException($subject, $violations);
        }
    }

    /**
     * @template TBehavior of 'none'|'throw'|'log'
     *
     * @param TBehavior $behavior
     *
     * @throws ObjectNotFound
     */
    private function handleObjectNotFound(ObjectNotFound $exception, string $behavior): void
    {
        if ($behavior === 'throw') {
            throw $exception;
        }

        if ($behavior === 'log') {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    private function getValidatedId(CollectionInterface $subject): string
    {
        $id = $subject->getTypesenseId();

        if ($id !== urlencode($id)) {
            throw new InvalidSchemaException(sprintf(
                'The provided ID "%s" must not require URL encoding.',
                $id,
            ));
        }

        return $id;
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
