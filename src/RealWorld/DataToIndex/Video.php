<?php

declare(strict_types=1);

namespace App\RealWorld\DataToIndex;

final readonly class Video
{
    public function __construct(
        public string $title,
        public string $author,
        public float $length,
        public ?string $description = null,
        public ?string $transcript = null,
    ) {}
}
