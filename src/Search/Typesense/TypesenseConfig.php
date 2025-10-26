<?php

declare(strict_types=1);

namespace App\Search\Typesense;

final readonly class TypesenseConfig
{
    public function __construct(
        public string $apiKey,
        public string $scheme = 'http',
        public string $host = '127.0.0.1',
        public int $port = 8108,
        public string $path = '',
    ) {}
}
