<?php

declare(strict_types=1);

namespace Foundation\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The Git repositories the Install application page offers applications from, on every host.
 */
class CatalogSettings extends Settings
{
    /**
     * The hosts clone them one after another within one request, so there can't be many.
     */
    public const int MaxRepositories = 10;

    /**
     * Each has a repository and a branch or tag. It's not a var tag, which laravel-settings
     * would read as a cast, and it can't cast a list of arrays.
     *
     * @phpstan-var list<array{repository: string, reference: string}>
     */
    public array $repositories = [];

    public static function group(): string
    {
        return 'catalog';
    }
}
