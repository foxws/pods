<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;

class EnvironmentOverviewController implements HasMiddleware
{
    use ReadsFromHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function __invoke(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Environments/EnvironmentOverview', [
            'environment' => EnvironmentData::fromModel($environment),
            'host' => Inertia::defer(fn (): array => $this->fromHost(fn () => $lpod->host($environment))),
            'applications' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->applications($environment))),
            'secrets' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->secrets($environment)), 'secrets'),
            'events' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->events($environment)), 'events'),
            'images' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->images($environment)), 'secrets'),
        ]);
    }
}
