<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A systemd timer lpod wrote for an application, which starts one of its containers on a schedule,
 * with when it runs next, when it last ran and how that ended.
 */
#[TypeScript]
class TimerData extends Data
{
    /**
     * @param  list<string>  $on_calendar
     * @param  string|null  $last_result  the result of the container's service after its last run (success, exit-code, …), null before the first run and while it runs
     */
    public function __construct(
        public string $name,
        public string $unit,
        public string $container,
        public string $service,
        public array $on_calendar,
        public ?bool $persistent,
        public ?string $randomized_delay_sec,
        public ?string $timeout_start_sec,
        public bool $enabled,
        public bool $running,
        public ?string $next_run,
        public ?string $last_run,
        public ?string $last_result,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            unit: (string) $payload['unit'],
            container: (string) $payload['container'],
            service: (string) $payload['service'],
            on_calendar: array_values(array_map(strval(...), $payload['on_calendar'] ?? [])),
            persistent: isset($payload['persistent']) ? (bool) $payload['persistent'] : null,
            randomized_delay_sec: isset($payload['randomized_delay_sec']) ? (string) $payload['randomized_delay_sec'] : null,
            timeout_start_sec: isset($payload['timeout_start_sec']) ? (string) $payload['timeout_start_sec'] : null,
            enabled: (bool) ($payload['enabled'] ?? false),
            running: (bool) ($payload['running'] ?? false),
            next_run: $payload['next_run'] ?? null,
            last_run: $payload['last_run'] ?? null,
            last_result: $payload['last_result'] ?? null,
        );
    }
}
