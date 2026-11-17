<?php

declare(strict_types=1);

namespace Domain\Environments\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Something on a host the search palette can jump to.
 */
#[TypeScript]
class SearchResultData extends Data
{
    public function __construct(
        public string $group,
        public string $label,
        public ?string $description,
        public string $url,
    ) {}
}
