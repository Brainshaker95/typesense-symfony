<?php

declare(strict_types=1);

namespace App\Search\Exception;

use App\Search\Exception\Traits\WrappedThrowableTrait;
use LogicException;

final class UnreachableException extends LogicException
{
    use WrappedThrowableTrait;
}
