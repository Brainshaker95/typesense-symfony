<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

return static fn (array $context): KernelInterface => new Kernel(
    \array_key_exists('APP_ENV', $context) && \is_string($context['APP_ENV']) ? $context['APP_ENV'] : 'prod',
    \array_key_exists('APP_DEBUG', $context) && (bool) $context['APP_DEBUG'],
);
