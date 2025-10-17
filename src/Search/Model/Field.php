<?php

declare(strict_types=1);

namespace App\Search\Model;

use App\Search\Model\Support\ArrayableInterface;
use App\Search\Model\Support\ArrayableTrait;

final class Field implements ArrayableInterface
{
    use ArrayableTrait;

    /**
     * @see https://typesense.org/docs/29.0/api/collections.html#field-types
     */
    public const string TYPE_AUTO           = 'auto';
    public const string TYPE_BOOL           = 'bool';
    public const string TYPE_BOOL_ARRAY     = 'bool[]';
    public const string TYPE_FLOAT          = 'float';
    public const string TYPE_FLOAT_ARRAY    = 'float[]';
    public const string TYPE_GEOPOINT       = 'geopoint';
    public const string TYPE_GEOPOINT_ARRAY = 'geopoint[]';
    public const string TYPE_GEOPOLYGON     = 'geopolygon';
    public const string TYPE_IMAGE          = 'image';
    public const string TYPE_INT32          = 'int32';
    public const string TYPE_INT32_ARRAY    = 'int32[]';
    public const string TYPE_INT64          = 'int64';
    public const string TYPE_INT64_ARRAY    = 'int64[]';
    public const string TYPE_OBJECT         = 'object';
    public const string TYPE_OBJECT_ARRAY   = 'object[]';
    public const string TYPE_STRING         = 'string';
    public const string TYPE_STRING_ARRAY   = 'string[]';
    public const string TYPE_STRING_AUTO    = 'string*';

    /**
     * @see https://typesense.org/docs/29.0/api/collections.html#field-parameters
     *
     * @param self::TYPE_* $type
     * @param ?non-empty-string $locale
     * @param ?('cosine'|'ip') $vec_dist
     * @param ?non-empty-string $reference
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?bool $facet = null,
        public ?bool $optional = null,
        public ?bool $index = null,
        public ?bool $store = null,
        public ?bool $sort = null,
        public ?bool $infix = null,
        public ?string $locale = null {
            get => $this->locale;
            // @throws InvalidSchemaException
            set(?string $locale) {
                $this->locale = $locale;

                if ($this->locale !== null) {
                    $this->name .= '_' . $this->locale;
                }
            }
        },
        public ?int $num_dim = null,
        public ?string $vec_dist = null,
        public ?string $reference = null,
        public ?bool $range_index = null,
        public ?bool $stem = null,
    ) {}
}
