<?php

declare(strict_types=1);

namespace Domain\Containers\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContainerDetailData extends Data
{
    /**
     * @param  array{command: string, interval: string|null, last_check: string|null}|null  $health_check
     * @param  list<ContainerMountData>  $mounts
     * @param  list<array{name: string, ip: string|null}>  $networks
     * @param  list<array{name: string, type: string, target: string}>  $secrets
     * @param  list<array{name: string, value: string}>  $environment
     */
    public function __construct(
        public HostContainerData $container,
        public ?string $created_at,
        public ?int $memory_limit,
        public ?string $restart_policy,
        public int $restarts,
        public ?string $user,
        public ?string $auto_update,
        public ?string $digest,
        public ?array $health_check,
        public array $mounts,
        public array $networks,
        public array $secrets,
        public array $environment,
    ) {}

    /**
     * Secret values never leave the host: lpod only reports where a secret is exposed.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $healthCheck = $payload['health_check'] ?? null;

        return new self(
            container: HostContainerData::fromLpod($payload),
            created_at: $payload['created_at'] ?? null,
            memory_limit: isset($payload['memory_limit']) ? (int) $payload['memory_limit'] : null,
            restart_policy: $payload['restart_policy'] ?? null,
            restarts: (int) ($payload['restarts'] ?? 0),
            user: $payload['user'] ?? null,
            auto_update: $payload['auto_update'] ?? null,
            digest: $payload['digest'] ?? null,
            health_check: is_array($healthCheck) ? [
                'command' => (string) ($healthCheck['command'] ?? ''),
                'interval' => $healthCheck['interval'] ?? null,
                'last_check' => $healthCheck['last_check'] ?? null,
            ] : null,
            mounts: array_map(ContainerMountData::fromLpod(...), array_values($payload['mounts'] ?? [])),
            networks: array_values(array_map(fn (array $network): array => [
                'name' => (string) $network['name'],
                'ip' => $network['ip'] ?? null,
            ], $payload['networks'] ?? [])),
            secrets: array_values(array_map(fn (array $secret): array => [
                'name' => (string) $secret['name'],
                'type' => (string) ($secret['type'] ?? 'env'),
                'target' => (string) ($secret['target'] ?? $secret['name']),
            ], $payload['secrets'] ?? [])),
            environment: array_values(array_map(fn (array $variable): array => [
                'name' => (string) $variable['name'],
                'value' => (string) ($variable['value'] ?? ''),
            ], $payload['environment'] ?? [])),
        );
    }
}
