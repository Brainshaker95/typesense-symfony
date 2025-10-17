<?php

declare(strict_types=1);

namespace App\Search\Model\Support;

use function array_filter;
use function get_object_vars;

/**
 * @template TArrayRepresentation of array<non-empty-string, mixed> = array<non-empty-string, mixed>
 */
trait ArrayableTrait
{
    /**
     * @return TArrayRepresentation
     */
    public function toArray(): array
    {
        /**
         * @var TArrayRepresentation $array
         */
        $array = array_filter(
            get_object_vars($this),
            static fn (mixed $value): bool => $value !== null,
        );

        return $array;
    }
}
