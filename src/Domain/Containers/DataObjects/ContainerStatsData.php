<?php

declare(strict_types=1);

namespace Domain\Containers\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One sample of a running container's use. The I/O values are totals since it started, so rates take two samples.
 */
#[TypeScript]
class ContainerStatsData extends Data
{
    public function __construct(
        public ?float $cpu,
        public ?int $memory,
        public ?int $memory_limit,
        public ?int $net_in,
        public ?int $net_out,
        public ?int $block_in,
        public ?int $block_out,
        public ?int $pids,
        public ?string $sampled_at,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $integer = fn (string $key): ?int => is_numeric($payload[$key] ?? null) ? (int) $payload[$key] : null;

        return new self(
            cpu: is_numeric($payload['cpu'] ?? null) ? (float) $payload['cpu'] : null,
            memory: $integer('memory'),
            memory_limit: $integer('memory_limit'),
            net_in: $integer('net_in'),
            net_out: $integer('net_out'),
            block_in: $integer('block_in'),
            block_out: $integer('block_out'),
            pids: $integer('pids'),
            sampled_at: $payload['sampled_at'] ?? null,
        );
    }
}
