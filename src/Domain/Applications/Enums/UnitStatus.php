<?php

declare(strict_types=1);

namespace Domain\Applications\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum UnitStatus: string implements Enumerable
{
    use HasLabels;

    case Running = 'running';
    case Activating = 'activating';
    case Deactivating = 'deactivating';
    case Reloading = 'reloading';
    case Failed = 'failed';
    case Stopped = 'inactive';
    case Unknown = 'unknown';

    public static function fromLpod(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Activating => __('Starting'),
            self::Deactivating => __('Stopping'),
            self::Reloading => __('Reloading'),
            self::Failed => __('Failed'),
            self::Stopped => __('Stopped'),
            self::Unknown => __('Unknown'),
        };
    }

    public function isTransitioning(): bool
    {
        return in_array($this, [self::Activating, self::Deactivating, self::Reloading], true);
    }
}
