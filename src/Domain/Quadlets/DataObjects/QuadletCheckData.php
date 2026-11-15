<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The result of running Quadlet files through podman quadlet --dryrun.
 */
#[TypeScript]
class QuadletCheckData extends Data
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $units
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $units,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            valid: (bool) ($payload['valid'] ?? false),
            errors: array_values(array_map(strval(...), $payload['errors'] ?? [])),
            units: array_values(array_map(strval(...), $payload['units'] ?? [])),
        );
    }
}
