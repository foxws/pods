<?php

declare(strict_types=1);

namespace Domain\Users\Enums;

use Domain\Shared\Enums\HostResource;
use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a user can pin. Host resources are pinned by name, as they have no row of their own in Pods.
 */
#[TypeScript]
enum PinType: string implements Enumerable
{
    use HasLabels;

    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::Application => __('Application'),
        };
    }

    /**
     * The host resource a pin of this type names, which decides the names it accepts.
     */
    public function hostResource(): HostResource
    {
        return match ($this) {
            self::Application => HostResource::Application,
        };
    }
}
