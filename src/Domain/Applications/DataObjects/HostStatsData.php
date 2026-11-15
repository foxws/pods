<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HostStatsData extends Data
{
    public function __construct(
        public int $cpu_cores,
        public float $cpu_usage,
        public int $memory_used,
        public int $memory_total,
        public int $disk_used,
        public int $disk_total,
        public int $containers_running,
        public int $containers_total,
        public ?string $auto_update_next_run,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            cpu_cores: (int) data_get($payload, 'cpu.cores', 0),
            cpu_usage: (float) data_get($payload, 'cpu.usage', 0),
            memory_used: (int) data_get($payload, 'memory.used', 0),
            memory_total: (int) data_get($payload, 'memory.total', 0),
            disk_used: (int) data_get($payload, 'disk.used', 0),
            disk_total: (int) data_get($payload, 'disk.total', 0),
            containers_running: (int) data_get($payload, 'containers.running', 0),
            containers_total: (int) data_get($payload, 'containers.total', 0),
            auto_update_next_run: data_get($payload, 'auto_update.enabled') ? data_get($payload, 'auto_update.next_run') : null,
        );
    }
}
