<?php

declare(strict_types=1);

namespace Modules\Web\Volumes\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Domain\Volumes\DataObjects\VolumeExportData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;

class VolumeController implements HasMiddleware
{
    use ReadsFromHost;
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Volumes/VolumeIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'volumes' => $this->deferFromHost(fn (): array => $lpod->volumes($environment), live: $this->changesTo($environment, 'volumes')),
            // Slow (lpod walks every file), so in a group of its own, and neither live nor polled.
            'sizes' => $this->deferFromHost(fn (): array => $lpod->volumeSizes($environment), 'sizes'),
            'exports' => fn (): array => VolumeExportData::collect($environment->volumeExports()->latest('id')->limit(25)->get())->all(),
        ]);
    }

    public function destroy(Environment $environment, string $volume, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->removeVolume($environment, $volume),
            success: __('Volume removed'),
            description: __(':name was removed from Podman.', ['name' => $volume]),
            failure: __('Volume not removed'),
        );

        return back();
    }
}
