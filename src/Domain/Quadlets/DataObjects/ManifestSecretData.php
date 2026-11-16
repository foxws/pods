<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How Pods asks for a secret the units use. A secret with "from" and "key" is filled in
 * from a key of another secret whose format is "dotenv", such as DB_PASSWORD from the app's .env.
 */
#[TypeScript]
class ManifestSecretData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $format,
        public bool $generate,
        public ?string $from,
        public ?string $key,
    ) {}
}
