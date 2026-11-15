<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

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
        ]);
    }

    public function show(Environment $environment, string $application, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Applications/ApplicationShow', [
            'environment' => EnvironmentData::fromModel($environment),
            'name' => $application,
            'application' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->application($environment, $application))),
            'files' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->files($environment, $application)), 'details'),
            'logs' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->logs($environment, $application)), 'details'),
        ]);
    }

    public function create(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('operate', $environment);

        return Inertia::render('Applications/ApplicationCreate', [
            'environment' => EnvironmentData::fromModel($environment),
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

        return to_action([self::class, 'index'], $environment);
    }
}
