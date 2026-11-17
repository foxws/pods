<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What lpod's "APP ondemand enable" takes: the socket and app ports, the idle timeout, and for apps with
 * background work (such as Laravel's queue and scheduler) the workers, the idle check and the scheduler.
 */
#[TypeScript]
class OnDemandSettingsData extends Data
{
    /**
     * @param  list<string>  $workers
     */
    public function __construct(
        public ?int $port,
        public ?int $app_port,
        public ?string $listen,
        public string $idle_timeout,
        public array $workers,
        public ?OnDemandIdleCheckData $idle_check,
        public ?OnDemandScheduleData $schedule,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            port: isset($payload['port']) ? (int) $payload['port'] : null,
            app_port: isset($payload['app_port']) ? (int) $payload['app_port'] : null,
            listen: $payload['listen'] ?? null,
            idle_timeout: (string) ($payload['idle_timeout'] ?? '10min'),
            workers: array_values(array_map(strval(...), $payload['workers'] ?? [])),
            idle_check: isset($payload['idle_check']) ? OnDemandIdleCheckData::fromLpod($payload['idle_check']) : null,
            schedule: isset($payload['schedule']) ? OnDemandScheduleData::fromLpod($payload['schedule']) : null,
        );
    }

    /**
     * The options of "lpod APP ondemand enable", as a list.
     *
     * @return list<string>
     */
    public function toLpodArguments(): array
    {
        $arguments = ['--port', (string) $this->port, '--app-port', (string) $this->app_port];

        if ($this->listen !== null) {
            array_push($arguments, '--listen', $this->listen);
        }

        array_push($arguments, '--idle-timeout', $this->idle_timeout);

        foreach ($this->workers as $worker) {
            array_push($arguments, '--worker', $worker);
        }

        if ($this->idle_check !== null) {
            array_push($arguments, '--idle-container', $this->idle_check->container);

            if ($this->idle_check->user !== null) {
                array_push($arguments, '--idle-user', $this->idle_check->user);
            }

            array_push($arguments, '--idle-command', $this->idle_check->command);
        }

        if ($this->schedule !== null) {
            array_push($arguments, '--schedule', $this->schedule->container);

            foreach ($this->schedule->at as $time) {
                array_push($arguments, '--schedule-at', $time);
            }

            foreach ($this->schedule->environment as $key => $value) {
                array_push($arguments, '--schedule-env', "{$key}={$value}");
            }
        }

        return $arguments;
    }
}
