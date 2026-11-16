<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A directory of Quadlet files that a pods.yml offers, such as "production" or "development".
 */
#[TypeScript]
class ManifestSourceData extends Data
{
    public function __construct(
        public string $name,
        public string $path,
        public ?string $description,
    ) {}
}
