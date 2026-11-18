<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A container of the application that a systemd timer starts on a schedule, such as a job that
 * fetches new data every hour. lpod writes it as APP-NAME.timer. The keys follow the systemd
 * directives they become: OnCalendar=, Persistent=, RandomizedDelaySec= and TimeoutStartSec=.
 */
#[TypeScript]
class ManifestTimerData extends Data
{
    public const string NamePattern = '/^[a-z0-9][a-z0-9_-]{0,31}$/';

    /**
     * @param  list<string>  $on_calendar  systemd calendar events, such as hourly or *-*-* 02:00
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public string $container,
        public array $on_calendar,
        public ?bool $persistent,
        public ?string $randomized_delay_sec,
        public ?string $timeout_start_sec,
    ) {}

    /**
     * What lpod APP timers set takes for this timer.
     *
     * @return array{name: string, container: string, on_calendar: list<string>, persistent: ?bool, randomized_delay_sec: ?string, timeout_start_sec: ?string}
     */
    public function forLpod(): array
    {
        return [
            'name' => $this->name,
            'container' => $this->container,
            'on_calendar' => $this->on_calendar,
            'persistent' => $this->persistent,
            'randomized_delay_sec' => $this->randomized_delay_sec,
            'timeout_start_sec' => $this->timeout_start_sec,
        ];
    }
}
