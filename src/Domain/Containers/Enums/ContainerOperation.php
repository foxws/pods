<?php

declare(strict_types=1);

namespace Domain\Containers\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContainerOperation: string implements Enumerable
{
    use HasLabels;

    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';

    public function label(): string
    {
        return match ($this) {
            self::Start => __('Start'),
            self::Stop => __('Stop'),
            self::Restart => __('Restart'),
        };
    }
}
