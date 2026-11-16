<?php

declare(strict_types=1);

namespace Domain\Environments\DataObjects;

use Domain\Applications\DataObjects\HostStatsData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The settings of a host that lpod manages: the podman-auto-update timer and lingering.
 */
#[TypeScript]
class HostSettingsData extends Data
{
    public function __construct(
        public bool $auto_update,
        public ?string $schedule,
        public ?bool $rollback,
        public ?string $next_run,
        public ?string $last_run,
        public ?bool $linger,
        public ?string $quadlet_dir,
        public bool $editable,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $autoUpdate = $payload['auto_update'] ?? [];

        return new self(
            auto_update: (bool) ($autoUpdate['enabled'] ?? false),
            schedule: (string) ($autoUpdate['schedule'] ?? 'daily'),
            rollback: (bool) ($autoUpdate['rollback'] ?? true),
            next_run: $autoUpdate['next_run'] ?? null,
            last_run: $autoUpdate['last_run'] ?? null,
            linger: (bool) ($payload['linger'] ?? false),
            quadlet_dir: $payload['quadlet_dir'] ?? null,
            editable: true,
        );
    }

    /**
     * What an lpod without the settings command reports. It doesn't know the schedule or rollback, and older releases don't report lingering either.
     */
    public static function fromHostStats(HostStatsData $stats): self
    {
        return new self(
            auto_update: $stats->auto_update_enabled,
            schedule: null,
            rollback: null,
            next_run: $stats->auto_update_next_run,
            last_run: null,
            linger: $stats->linger,
            quadlet_dir: $stats->quadlet_dir,
            editable: false,
        );
    }
}
