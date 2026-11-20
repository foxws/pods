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
            'host' => $this->deferFromHost(fn () => $lpod->host($environment), live: $environment),
            'applications' => $this->deferFromHost(fn (): array => $lpod->applications($environment), live: $environment),
            'secrets' => $this->deferFromHost(fn (): array => $lpod->secrets($environment), 'secrets', live: $environment),
            'events' => $this->deferFromHost(fn (): array => $lpod->events($environment), 'events', live: $environment),
            'images' => $this->deferFromHost(fn (): array => $lpod->images($environment), 'secrets', live: $environment),
        ]);
    }
}
