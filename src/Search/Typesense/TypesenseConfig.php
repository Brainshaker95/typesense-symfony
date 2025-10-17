<?php

declare(strict_types=1);

namespace App\Search\Typesense;

final readonly class TypesenseConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $scheme = 'http',
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 8108,
        public readonly string $path = '',
    ) {}
}
