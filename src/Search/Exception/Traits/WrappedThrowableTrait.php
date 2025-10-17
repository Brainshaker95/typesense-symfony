<?php

declare(strict_types=1);

namespace App\Search\Exception\Traits;

use Throwable;

trait WrappedThrowableTrait
{
    public static function wrap(Throwable $throwable): self
    {
        return $throwable instanceof self
            ? $throwable
            : new self(
                message: $throwable->getMessage(),
                code: (int) $throwable->getCode(),
                previous: $throwable,
            );
    }
}
