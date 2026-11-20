<?php

declare(strict_types=1);

namespace Domain\Volumes\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum VolumeExportStatus: string implements Enumerable
{
    use HasLabels;

    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Running => __('Exporting'),
            self::Completed => __('Ready'),
            self::Failed => __('Failed'),
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
