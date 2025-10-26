<?php

declare(strict_types=1);

namespace App\Twig;

use Stringable;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

use function array_map;
use function array_replace;
use function http_build_query;
use function is_bool;

final readonly class UrlExtension
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    /**
     * @param array<non-empty-string, bool|int|float|string|Stringable> $queryParameters
     *
     * @throws BadRequestException
     */
    #[AsTwigFunction(name: 'modify_query_string')]
    public function modifyQueryString(array $queryParameters): string
    {
        $mainRequest = $this->requestStack->getMainRequest();

        $currentQueryParameters = $mainRequest instanceof Request
            ? $mainRequest->query->all()
            : [];

        $queryParameters = array_replace($currentQueryParameters, array_map(
            static fn (bool|int|float|string|Stringable $value): string => match (true) {
                is_bool($value) => $value ? '1' : '0',
                default         => (string) $value,
            },
            $queryParameters,
        ));

        $queryString = http_build_query($queryParameters);

        return $queryString === '' ? '' : '?' . $queryString;
    }
}
