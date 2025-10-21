<?php

declare(strict_types=1);

namespace App\Search\Repository;

use App\RealWorld\DataToIndex\DefaultPage;
use App\RealWorld\DataToIndex\LocalizedPage;
use App\RealWorld\Repository\DataRepository;
use App\Search\Collection\CollectionInterface;
use App\Search\Collection\Content;
use Override;
use Random\RandomException;
use Symfony\Component\Console\Output\OutputInterface;

use function random_int;
use function sprintf;
use function Symfony\Component\String\s;

/**
 * @phpstan-type Collection Content
 * @phpstan-type Transformed DefaultPage|LocalizedPage
 *
 * @phpstan-implements RepositoryInterface<Collection, Transformed>
 */
final readonly class ContentRepository implements RepositoryInterface
{
    public function __construct(
        private DataRepository $dataRepository,
    ) {}

    #[Override]
    public function supports(CollectionInterface $collection): bool
    {
        return $collection instanceof Content;
    }

    /**
     * @throws RandomException
     */
    #[Override]
    public function getData(?OutputInterface $output = null): array
    {
        $pages     = $this->dataRepository->getPages();
        $subjects  = [];
        $deletions = [];

        foreach ($pages as $page) {
            $isLocalizedPage = $page instanceof LocalizedPage;

            $id = sprintf(
                '%s%s',
                $page->id,
                $isLocalizedPage ? '_' . $page->locale : '',
            );

            $subject = new Content(
                id: $id,
                title: $page->title,
                content: $page->content,
                locale: $isLocalizedPage ? $page->locale : null,
            );

            if (random_int(1, 100) <= 50) {
                $subjects[] = $subject;
            } else {
                $deletions[] = $subject;
            }
        }

        return [
            'upserts'   => $subjects,
            'deletions' => $deletions,
        ];
    }

    /**
     * @return Transformed
     */
    #[Override]
    public function transform(CollectionInterface $subject, array $hit): mixed
    {
        return $subject->locale === null
            ? new DefaultPage(
                id: (int) $subject->id,
                title: $subject->title,
                content: $subject->content,
            )
            : new LocalizedPage(
                id: (int) s($subject->id)->replace('_' . $subject->locale, '')->toString(),
                title: $subject->title,
                content: $subject->content,
                locale: $subject->locale,
            );
    }
}
