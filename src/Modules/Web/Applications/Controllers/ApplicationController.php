<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\SuggestOnDemandSettings;
use Domain\Applications\Actions\SyncInstallation;
use Domain\Applications\DataObjects\InstallationData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Catalog\Actions\ListCatalog;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Requests\DestroyApplicationRequest;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationController implements HasMiddleware
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

        return Inertia::render('Applications/ApplicationIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'applications' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->applications($environment))),
            'host' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->host($environment))),
            'images' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->images($environment)), 'images'),
        ]);
    }

    public function show(
        Environment $environment,
        string $application,
        LpodClient $lpod,
        SuggestOnDemandSettings $suggestOnDemandSettings,
        SyncInstallation $syncInstallation,
    ): Response {
        Gate::authorize('view', $environment);

        /** @var list<QuadletFileData>|null $files */
        $files = null;

        // The files, the on-demand suggestion and the installation load together, so read the files once.
        $readFiles = function () use (&$files, $environment, $application, $lpod): array {
            return $files ??= $lpod->files($environment, $application);
        };

        return Inertia::render('Applications/ApplicationShow', [
            'environment' => EnvironmentData::fromModel($environment),
            'name' => $application,
            'application' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->application($environment, $application))),
            'ondemand' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->onDemand($environment, $application))),
            'ondemandSuggestion' => Inertia::defer(fn (): array => $this->fromHost(fn () => $suggestOnDemandSettings(
                $lpod->application($environment, $application)->application,
                $readFiles(),
                $environment->installations()->firstWhere('application', $application)?->manifest(),
            )), 'details'),
            'files' => Inertia::defer(fn (): array => $this->fromHost($readFiles), 'details'),
            'installation' => Inertia::defer(fn (): array => $this->fromHost(function () use ($environment, $application, $syncInstallation, $readFiles): ?InstallationData {
                $installation = $environment->installations()->firstWhere('application', $application);

                return $installation !== null ? InstallationData::fromModel($syncInstallation($installation, $readFiles())) : null;
            }), 'details'),
            'logs' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->logs($environment, $application)), 'details'),
            'secrets' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->secrets($environment)), 'details'),
        ]);
    }

    public function create(Environment $environment, LpodClient $lpod, ListCatalog $listCatalog): Response
    {
        Gate::authorize('operate', $environment);

        return Inertia::render('Applications/ApplicationCreate', [
            'environment' => EnvironmentData::fromModel($environment),
            'catalog' => Inertia::defer(fn (): array => $this->fromHost(fn () => $listCatalog($environment)), 'catalog'),
            'secrets' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->secrets($environment))),
        ]);
    }

    public function destroy(DestroyApplicationRequest $request, Environment $environment, string $application, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $removed = $this->toHost(
            fn () => $lpod->removeApplication(
                $environment,
                $application,
                volumes: $request->boolean('volumes'),
                images: $request->boolean('images'),
                secrets: $request->boolean('secrets'),
            ),
            success: __(':application removed', ['application' => $application]),
            description: __('Its units are stopped and its Quadlet files are gone.'),
            failure: __(':application not removed', ['application' => $application]),
        );

        if (! $removed) {
            return back();
        }

        $environment->installations()->where('application', $application)->delete();

        return to_action([self::class, 'index'], $environment);
    }
}
