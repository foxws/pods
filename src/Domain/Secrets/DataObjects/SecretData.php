<?php

declare(strict_types=1);

namespace Domain\Secrets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SecretData extends Data
{
    public const string NamePattern = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,252}$/';

    /**
     * @param  list<string>  $used_by
     */
    public function __construct(
        public string $name,
        public ?string $driver,
        public ?string $updated_at,
        public array $used_by,
        public bool $missing,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload, bool $missing = false): self
    {
        return new self(
            name: (string) $payload['name'],
            driver: $payload['driver'] ?? null,
            updated_at: $payload['updated_at'] ?? null,
            used_by: array_values(array_map(strval(...), $payload['used_by'] ?? [])),
            missing: $missing,
        );
    }
}
