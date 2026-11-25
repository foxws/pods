<?php

declare(strict_types=1);

namespace Domain\Shared\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How often an idle host page reloads, in seconds, as GeneralSettings::$host_refresh keeps it.
 */
#[TypeScript]
enum HostRefresh: int implements Enumerable
{
    use HasLabels;

    case Off = 0;
    case ThreeSeconds = 3;
    case FiveSeconds = 5;
    case TenSeconds = 10;
    case FifteenSeconds = 15;
    case ThirtySeconds = 30;
    case OneMinute = 60;
    case ThreeMinutes = 180;
    case FiveMinutes = 300;

    public function label(): string
    {
        return match ($this) {
            self::Off => __('Off'),
            self::ThreeSeconds => __('Every 3 seconds'),
            self::FiveSeconds => __('Every 5 seconds'),
            self::TenSeconds => __('Every 10 seconds'),
            self::FifteenSeconds => __('Every 15 seconds'),
            self::ThirtySeconds => __('Every 30 seconds'),
            self::OneMinute => __('Every minute'),
            self::ThreeMinutes => __('Every 3 minutes'),
            self::FiveMinutes => __('Every 5 minutes'),
        };
    }
}
