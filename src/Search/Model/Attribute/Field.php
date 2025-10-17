<?php

declare(strict_types=1);

namespace App\Search\Model\Attribute;

use App\Search\Model\Field as ModelField;
use Attribute;

#[Attribute(flags: Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /**
     * @param ?ModelField::TYPE_* $type
     * @param bool|'asc'|'desc'|null $sort
     */
    public function __construct(
        public ?string $type = null,
        public bool|string|null $sort = null,
        public ?int $sortPriority = null,
        public ?bool $isDefaultSortingField = null,
        public ?bool $query = null,
        public ?int $queryPriority = null,
    ) {}
}
