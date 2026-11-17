<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The scheduler of an on-demand application, which only runs at the given minutes (in UTC).
 */
#[TypeScript]
class OnDemandScheduleData extends Data
{
    /**
     * @param  list<string>  $at
     * @param  array<string, string>  $environment
     */
    public function __construct(
        public string $container,
        public array $at,
        public array $environment,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            container: (string) $payload['container'],
            at: array_values(array_map(strval(...), $payload['at'] ?? [])),
            environment: array_map(strval(...), $payload['environment'] ?? []),
        );
    }
}
