<?php

declare(strict_types=1);

namespace App\RealWorld\DataToIndex;

final readonly class Image
{
    public function __construct(
        public string $title,
        public string $author,
        public ?string $description = null,
        public ?string $caption = null,
    ) {}
}
