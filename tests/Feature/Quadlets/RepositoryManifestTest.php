<?php

declare(strict_types=1);

use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\DataObjects\ManifestSetupStepData;

/**
 * The containers a manifest names, by their Quadlet's name, for its steps, on-demand and routes.
 *
 * @return list<string>
 */
function containersNamedIn(ManifestData $manifest): array
{
    return collect([...$manifest->after_install, ...$manifest->after_update])
        ->map(fn (ManifestSetupStepData $step): string => $step->container)
        ->merge($manifest->ondemand?->workers ?? [])
        ->push($manifest->ondemand?->idle_check?->container, $manifest->ondemand?->schedule?->container)
        ->merge(collect($manifest->routes)->map(fn ($route): string => (string) preg_replace('/^systemd-(.+):\d+$/', '$1', $route->upstream)))
        ->filter()
        ->unique()
        ->values()
        ->all();
}

it('reads the pods.yml of Pods\' own units and names only containers they have', function (string $manifestPath, string $unitsPath) {
    $manifest = ManifestData::fromYaml((string) file_get_contents(base_path($manifestPath)));

    expect(containersNamedIn($manifest))->not->toBeEmpty()
        ->each(fn ($container) => expect(base_path("{$unitsPath}/{$container->value}.container"))->toBeFile());
})->with([
    'production' => ['pods.yml', 'containers/quadlets/production'],
    'development' => ['containers/quadlets/development/pods.yml', 'containers/quadlets/development'],
]);

it('offers the source directories that exist', function () {
    $manifest = ManifestData::fromYaml((string) file_get_contents(base_path('pods.yml')));

    expect($manifest->sources)->toHaveCount(2)
        ->each(fn ($source) => expect(base_path($source->value->path))->toBeDirectory());
});
