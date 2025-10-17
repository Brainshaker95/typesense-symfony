<?php

declare(strict_types=1);

namespace App\Search\Model\Support;

/**
 * @template TArrayRepresentation of array<non-empty-string, mixed> = array<non-empty-string, mixed>
 */
interface ArrayableInterface
{
    /**
     * @return TArrayRepresentation
     */
    public function toArray(): array;
}
