<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigTest;

final class InstanceOfExtension
{
    /**
     * @param class-string $class
     */
    #[AsTwigTest(name: 'instanceof')]
    public function isInstanceOf(object $object, string $class): bool
    {
        return $object instanceof $class;
    }
}
