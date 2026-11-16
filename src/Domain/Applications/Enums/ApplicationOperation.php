<?php

declare(strict_types=1);

namespace Domain\Applications\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ApplicationOperation: string implements Enumerable
{
    use HasLabels;

    case Up = 'up';
    case Down = 'down';
    case ForceStop = 'force-stop';
    case Restart = 'restart';
    case Redeploy = 'redeploy';

    public function label(): string
    {
        return match ($this) {
            self::Up => __('Start'),
            self::Down => __('Stop'),
            self::ForceStop => __('Force stop'),
            self::Restart => __('Restart'),
            self::Redeploy => __('Redeploy'),
        };
    }
}
