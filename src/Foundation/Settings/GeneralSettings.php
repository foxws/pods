<?php

declare(strict_types=1);

namespace Foundation\Settings;

use Domain\Shared\Enums\Locale;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name = 'Laravel';

    public string $timezone = 'UTC';

    public Locale $default_locale = Locale::EnUs;

    public bool $allow_registration = false;

    /**
     * Seconds between reloads of an idle host page, for what changed outside Pods (0 turns them off).
     * Pods' own changes reach open pages at once, and a page reloads every 5 seconds while a unit changes state.
     */
    public int $host_refresh = 30;

    /**
     * The choices of host_refresh, in seconds.
     *
     * @var list<int>
     */
    public const array HostRefreshChoices = [0, 30, 60, 180, 300];

    public static function group(): string
    {
        return 'general';
    }
}
