<?php

declare(strict_types=1);

namespace Domain\Containers\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContainerMountData extends Data
{
    public function __construct(
        public string $type,
        public string $source,
        public string $destination,
        public ?string $mode,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            type: (string) ($payload['type'] ?? 'volume'),
            source: (string) $payload['source'],
            destination: (string) $payload['destination'],
            mode: $payload['mode'] ?? null,
        );
    }
}
