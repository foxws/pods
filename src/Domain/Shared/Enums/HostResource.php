<?php

declare(strict_types=1);

namespace Domain\Shared\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;

/**
 * The resources on a host that routes name, each by its route parameter. Names are passed to lpod
 * on the host, so only plain names match: never an option or a path.
 */
enum HostResource: string implements Enumerable
{
    use HasLabels;

    case Application = 'application';
    case Container = 'container';
    case Image = 'image';
    case Volume = 'volume';
    case Network = 'network';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Application => __('Application'),
            self::Container => __('Container'),
            self::Image => __('Image'),
            self::Volume => __('Volume'),
            self::Network => __('Network'),
            self::Secret => __('Secret'),
        };
    }

    /**
     * What a route accepts for the resource: its name, or an image's ID.
     */
    public function pattern(): string
    {
        return match ($this) {
            self::Application => '[A-Za-z0-9][A-Za-z0-9_.@-]{0,127}',
            self::Container, self::Volume, self::Network => '[A-Za-z0-9][A-Za-z0-9_.-]{0,127}',
            self::Image => '[a-f0-9]{12,64}',
            self::Secret => '[A-Za-z0-9][A-Za-z0-9_.-]{0,252}',
        };
    }

    /**
     * The pattern as a whole-string regex, for validation.
     */
    public function regex(): string
    {
        return '/^'.$this->pattern().'$/';
    }
}
