<?php

declare(strict_types=1);

namespace Modules\Web\Containers\Controllers;

use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;

class ContainerController implements HasMiddleware
{
    use ReadsFromHost;

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

        return Inertia::render('Containers/ContainerIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'containers' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->containers($environment))),
            'host' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->host($environment))),
        ]);
    }

    public function create(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('operate', $environment);

        return Inertia::render('Containers/ContainerCreate', [
            'environment' => EnvironmentData::fromModel($environment),
            'applications' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->applications($environment))),
            'networks' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->networks($environment))),
            'secrets' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->secrets($environment))),
        ]);
    }

    public function show(Environment $environment, string $container, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Containers/ContainerShow', [
            'environment' => EnvironmentData::fromModel($environment),
            'name' => $container,
            'container' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->container($environment, $container))),
            'logs' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->containerLogs($environment, $container)), 'details'),
            'stats' => Inertia::optional(fn (): array => $this->fromHost(fn () => $lpod->containerStats($environment, $container))),
            'quadlet' => Inertia::defer(fn (): array => $this->fromHost(fn (): ?QuadletFileData => $this->quadletOf($lpod, $environment, $container)), 'details'),
        ]);
    }

    /**
     * Gives the Quadlet file that defines the container, or null when it was started outside Quadlet.
     */
    protected function quadletOf(LpodClient $lpod, Environment $environment, string $container): ?QuadletFileData
    {
        $summary = $lpod->container($environment, $container)->container;

        if ($summary->quadlet === null || $summary->application === null) {
            return null;
        }

        return collect($lpod->files($environment, $summary->application))
            ->firstWhere('name', $summary->quadlet);
    }
}
