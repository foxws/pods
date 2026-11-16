<?php

declare(strict_types=1);

namespace Domain\Pods\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A Podman pod on the host, whether a .pod or .kube Quadlet manages it or not.
 */
#[TypeScript]
class PodData extends Data
{
    /**
     * @param  list<string>  $ports
     * @param  list<string>  $networks
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public ?string $application,
        public ?string $quadlet,
        public ?string $unit,
        public int $containers,
        public int $running,
        public array $ports,
        public array $networks,
        public ?string $created_at,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            name: (string) $payload['name'],
            status: (string) ($payload['status'] ?? 'unknown'),
            application: $payload['application'] ?? null,
            quadlet: $payload['quadlet'] ?? null,
            unit: $payload['unit'] ?? null,
            containers: (int) ($payload['containers'] ?? 0),
            running: (int) ($payload['running'] ?? 0),
            ports: array_values(array_map(strval(...), $payload['ports'] ?? [])),
            networks: array_values(array_map(strval(...), $payload['networks'] ?? [])),
            created_at: $payload['created_at'] ?? null,
        );
    }
}
