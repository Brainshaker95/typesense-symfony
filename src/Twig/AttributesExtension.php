<?php

declare(strict_types=1);

namespace App\Twig;

use Stringable;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Attribute\AsTwigFunction;

use function array_filter;
use function implode;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function Symfony\Component\String\s;

final readonly class AttributesExtension
{
    public function __construct(
        public HtmlSanitizerInterface $htmlSanitizer,
    ) {}

    /**
     * @param array<non-empty-string, bool|int|float|non-empty-string|Stringable|non-empty-list<string>|non-empty-list<Stringable>> $attributes
     */
    #[AsTwigFunction(name: 'attrs', isSafe: ['html'])]
    public function arrayToAttributeString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                $parts[] = $value ? $key : '';
            } elseif (is_int($value) || is_float($value)) {
                $parts[] = sprintf('%s="%d"', $key, $value);
            } elseif (is_string($value) || $value instanceof Stringable) {
                $value = s((string) $value)->toString();

                if ($value !== '') {
                    $parts[] = sprintf('%s="%s"', $key, $this->htmlSanitizer->sanitize($value));
                }
            } else {
                $subParts = [];

                foreach ($value as $valueInArray) {
                    $valueInArray = s((string) $valueInArray)->toString();

                    if ($valueInArray !== '') {
                        $subParts[] = $this->htmlSanitizer->sanitize($valueInArray);
                    }
                }

                if ($subParts !== []) {
                    $parts[] = sprintf('%s="%s"', $key, implode(' ', $subParts));
                }
            }
        }

        return implode(' ', array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        )) ?: '';
    }
}
