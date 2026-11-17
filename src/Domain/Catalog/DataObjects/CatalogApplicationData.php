<?php

declare(strict_types=1);

namespace Domain\Catalog\DataObjects;

use Domain\Quadlets\DataObjects\ManifestData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An application of a catalog repository: a directory with a pods.yml that describes it.
 * Installing it fetches its first source, or the directory itself when it has none.
 */
#[TypeScript]
class CatalogApplicationData extends Data
{
    public function __construct(
        public string $name,
        public string $repository,
        public string $reference,
        public string $path,
        public ManifestData $manifest,
    ) {}
}
