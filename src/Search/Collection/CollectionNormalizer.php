<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Exception\InvalidSchemaException;
use Override;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function array_find;
use function is_string;

final class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface
{
    use CollectionsTrait;

    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): ?string
    {
        try {
            return $data instanceof CollectionInterface ? $data::getSchema()->name : null;
        } catch (InvalidSchemaException $exception) {
            throw new LogicException('Attempted to normalize collection with invalid schema.', previous: $exception);
        }
    }

    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CollectionInterface;
    }

    #[Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        try {
            return is_string($data)
                ? array_find(
                    $this->collections,
                    static fn (CollectionInterface $collection): bool => $collection instanceof $type && $collection::getSchema()->name === $data,
                )
                : null;
        } catch (InvalidSchemaException $exception) {
            throw new LogicException('Attempted to denormalize collection with invalid schema.', previous: $exception);
        }
    }

    #[Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_string($data) && $type === CollectionInterface::class;
    }

    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            CollectionInterface::class => true,
        ];
    }
}
