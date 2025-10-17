<?php

declare(strict_types=1);

namespace App\Search\Collection;

use App\Search\Exception\InvalidPropertyException;
use App\Search\Exception\InvalidSchemaException;
use Override;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function array_find;
use function is_string;

final class CollectionNormalizer implements DenormalizerInterface, NormalizerInterface
{
    use CollectionsTrait;

    /**
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): ?string
    {
        return $data instanceof CollectionInterface
            ? $data::getSchema()->name
            : null;
    }

    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CollectionInterface;
    }

    /**
     * @throws InvalidPropertyException
     * @throws InvalidSchemaException
     */
    #[Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return is_string($data)
            ? array_find(
                $this->collections,
                static fn (CollectionInterface $collection): bool => $collection::getSchema()->name === $data,
            )
            : null;
    }

    #[Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === CollectionInterface::class;
    }

    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            CollectionInterface::class => true,
        ];
    }
}
