<?php

declare(strict_types=1);

namespace Domain\Images\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ImageData extends Data
{
    /**
     * @param  list<string>  $used_by
     */
    public function __construct(
        public string $id,
        public ?string $repository,
        public ?string $tag,
        public ?int $size,
        public ?string $created_at,
        public array $used_by,
        public int $containers,
        public bool $dangling,
        public ?string $auto_update,
        public ?string $update,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            repository: $payload['repository'] ?? null,
            tag: $payload['tag'] ?? null,
            size: isset($payload['size']) ? (int) $payload['size'] : null,
            created_at: $payload['created_at'] ?? null,
            used_by: array_values(array_map(strval(...), $payload['used_by'] ?? [])),
            containers: (int) ($payload['containers'] ?? 0),
            dangling: (bool) ($payload['dangling'] ?? false),
            auto_update: $payload['auto_update'] ?? null,
            update: $payload['update'] ?? null,
        );
    }
}
