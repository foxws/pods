<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The on-demand settings Pods suggests for an application: the ondemand section of its stored pods.yml,
 * or else what its Quadlet files suggest. Only a Laravel application gets workers and a scheduler.
 */
#[TypeScript]
class OnDemandSuggestionData extends Data
{
    /**
     * @param  list<string>  $containers
     */
    public function __construct(
        public bool $laravel,
        public array $containers,
        public OnDemandSettingsData $settings,
        public bool $from_manifest,
    ) {}
}
