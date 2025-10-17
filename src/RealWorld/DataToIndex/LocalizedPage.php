<?php

declare(strict_types=1);

namespace App\RealWorld\DataToIndex;

final readonly class LocalizedPage
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public string $locale,
    ) {}
}
