<?php

declare(strict_types=1);

namespace App\Search\Repository;

use App\RealWorld\DataToIndex\Image;
use App\RealWorld\DataToIndex\Video;
use App\RealWorld\Repository\DataRepository;
use App\Search\Collection\CollectionInterface;
use App\Search\Collection\Media;
use Override;

use function array_find;

/**
 * @phpstan-type Collection = Media
 * @phpstan-type Transformed = Image|Video
 *
 * @phpstan-implements RepositoryInterface<Collection, Transformed>
 */
final readonly class MediaRepository implements RepositoryInterface
{
    public function __construct(
        private DataRepository $dataRepository,
    ) {}

    #[Override]
    public function supports(CollectionInterface $collection): bool
    {
        return $collection instanceof Media;
    }

    #[Override]
    public function getData(): array
    {
        $images   = $this->dataRepository->getImages();
        $videos   = $this->dataRepository->getVideos();
        $subjects = [];

        foreach ($images as $image) {
            $subjects[] = new Media(
                type: 'image',
                title: $image->title,
                author: $image->author,
                description: $image->description,
                caption: $image->caption,
            );
        }

        foreach ($videos as $video) {
            $subjects[] = new Media(
                type: 'video',
                title: $video->title,
                author: $video->author,
                length: $video->length,
                description: $video->description,
                caption: $video->transcript,
            );
        }

        return [
            'upserts' => $subjects,
        ];
    }

    #[Override]
    public function transform(CollectionInterface $subject, array $hit): mixed
    {
        if ($subject->type === 'image') {
            return array_find(
                $this->dataRepository->getImages(),
                static fn (Image $image): bool => $image->title === $subject->title,
            );
        }

        if ($subject->type === 'video') {
            return array_find(
                $this->dataRepository->getVideos(),
                static fn (Video $video): bool => $video->title === $subject->title,
            );
        }

        return null;
    }
}
