<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContainerData extends Data
{
    public function __construct(
        public string $name,
        public ?string $image,
        public string $state,
        public ?float $cpu,
        public ?int $memory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            image: $payload['image'] ?? null,
            state: (string) ($payload['state'] ?? 'unknown'),
            cpu: isset($payload['cpu']) ? (float) $payload['cpu'] : null,
            memory: isset($payload['memory']) ? (int) $payload['memory'] : null,
        );
    }
}
