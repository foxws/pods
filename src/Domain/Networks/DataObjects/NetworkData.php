<?php

declare(strict_types=1);

namespace Domain\Networks\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class NetworkData extends Data
{
    /**
     * @param  list<string>  $applications
     */
    public function __construct(
        public string $name,
        public ?string $quadlet,
        public string $driver,
        public ?string $subnet,
        public ?string $gateway,
        public bool $dns,
        public bool $internal,
        public bool $ipv6,
        public int $containers,
        public array $applications,
        public bool $default,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            quadlet: $payload['quadlet'] ?? null,
            driver: (string) ($payload['driver'] ?? 'bridge'),
            subnet: $payload['subnet'] ?? null,
            gateway: $payload['gateway'] ?? null,
            dns: (bool) ($payload['dns'] ?? false),
            internal: (bool) ($payload['internal'] ?? false),
            ipv6: (bool) ($payload['ipv6'] ?? false),
            containers: (int) ($payload['containers'] ?? 0),
            applications: array_values(array_map(strval(...), $payload['applications'] ?? [])),
            default: (bool) ($payload['default'] ?? false),
        );
    }
}
