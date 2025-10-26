<?php

declare(strict_types=1);

namespace App\Search\Model\Support;

use function array_filter;
use function array_map;
use function get_object_vars;
use function is_array;

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
            array_map(
                static function (mixed $value): mixed {
                    if ($value instanceof ArrayableInterface) {
                        return $value->toArray();
                    }

                    if (!is_array($value)) {
                        return $value;
                    }

                    return array_map(
                        static fn (mixed $item) => $item instanceof ArrayableInterface ? $item->toArray() : $item,
                        $value,
                    );
                },
                get_object_vars($this),
            ),
            static fn (mixed $value): bool => $value !== null,
        );

        return $array;
    }
}
