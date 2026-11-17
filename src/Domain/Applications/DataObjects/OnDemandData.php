<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether an application starts on its first request and sleeps when idle, and the settings it was enabled with.
 */
#[TypeScript]
class OnDemandData extends Data
{
    public function __construct(
        public bool $enabled,
        public bool $listening,
        public bool $awake,
        public ?OnDemandSettingsData $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $enabled = (bool) ($payload['enabled'] ?? false);

        return new self(
            enabled: $enabled,
            listening: (bool) ($payload['listening'] ?? false),
            awake: (bool) ($payload['awake'] ?? false),
            settings: $enabled ? OnDemandSettingsData::fromLpod($payload) : null,
        );
    }
}
