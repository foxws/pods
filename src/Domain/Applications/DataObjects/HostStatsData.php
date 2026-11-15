<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HostStatsData extends Data
{
    /**
     * @param  list<float>|null  $cpu_load  The 1, 5 and 15 minute load averages.
     */
    public function __construct(
        public int $cpu_cores,
        public float $cpu_usage,
        public ?array $cpu_load,
        public int $memory_used,
        public int $memory_total,
        public ?int $swap_used,
        public ?int $swap_total,
        public int $disk_used,
        public int $disk_total,
        public int $containers_running,
        public int $containers_total,
        public bool $auto_update_enabled,
        public ?string $auto_update_next_run,
        public ?string $os,
        public ?string $kernel,
        public ?string $booted_at,
        public ?string $storage_driver,
        public ?string $network_backend,
        public ?string $quadlet_dir,
        public ?bool $linger,
        public ?int $units_active,
        public ?int $units_failed,
    ) {}

    /**
     * Reads the host stats. Fields that older lpod releases do not report stay null.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            cpu_cores: (int) data_get($payload, 'cpu.cores', 0),
            cpu_usage: (float) data_get($payload, 'cpu.usage', 0),
            cpu_load: is_array($load = data_get($payload, 'cpu.load')) ? array_values(array_map(floatval(...), $load)) : null,
            memory_used: (int) data_get($payload, 'memory.used', 0),
            memory_total: (int) data_get($payload, 'memory.total', 0),
            swap_used: self::nullableInt(data_get($payload, 'memory.swap_used')),
            swap_total: self::nullableInt(data_get($payload, 'memory.swap_total')),
            disk_used: (int) data_get($payload, 'disk.used', 0),
            disk_total: (int) data_get($payload, 'disk.total', 0),
            containers_running: (int) data_get($payload, 'containers.running', 0),
            containers_total: (int) data_get($payload, 'containers.total', 0),
            auto_update_enabled: (bool) data_get($payload, 'auto_update.enabled', false),
            auto_update_next_run: data_get($payload, 'auto_update.enabled') ? data_get($payload, 'auto_update.next_run') : null,
            os: data_get($payload, 'system.os'),
            kernel: data_get($payload, 'system.kernel'),
            booted_at: data_get($payload, 'system.booted_at'),
            storage_driver: data_get($payload, 'system.storage_driver'),
            network_backend: data_get($payload, 'system.network_backend'),
            quadlet_dir: data_get($payload, 'system.quadlet_dir'),
            linger: is_bool($linger = data_get($payload, 'system.linger')) ? $linger : null,
            units_active: self::nullableInt(data_get($payload, 'units.active')),
            units_failed: self::nullableInt(data_get($payload, 'units.failed')),
        );
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
