<?php

declare(strict_types=1);

namespace Domain\Users\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a stored notification shows: its icon and color on the notifications page.
 */
#[TypeScript]
enum NotificationLevel: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
