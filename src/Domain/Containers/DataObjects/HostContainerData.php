<?php

declare(strict_types=1);

namespace Domain\Containers\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A container on the host, whether a Quadlet manages it or not.
 */
#[TypeScript]
class HostContainerData extends Data
{
    /**
     * @param  list<string>  $ports
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $image,
        public string $state,
        public ?string $health,
        public ?int $exit_code,
        public ?string $application,
        public ?string $quadlet,
        public ?string $unit,
        public ?string $pod,
        public array $ports,
        public ?float $cpu,
        public ?int $memory,
        public ?string $started_at,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            name: (string) $payload['name'],
            image: $payload['image'] ?? null,
            state: (string) ($payload['state'] ?? 'unknown'),
            health: $payload['health'] ?? null,
            exit_code: isset($payload['exit_code']) ? (int) $payload['exit_code'] : null,
            application: $payload['application'] ?? null,
            quadlet: $payload['quadlet'] ?? null,
            unit: $payload['unit'] ?? null,
            pod: $payload['pod'] ?? null,
            ports: array_values(array_map(strval(...), $payload['ports'] ?? [])),
            cpu: isset($payload['cpu']) ? (float) $payload['cpu'] : null,
            memory: isset($payload['memory']) ? (int) $payload['memory'] : null,
            started_at: $payload['started_at'] ?? null,
        );
    }
}
