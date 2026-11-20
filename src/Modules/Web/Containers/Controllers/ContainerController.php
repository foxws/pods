<?php

declare(strict_types=1);

namespace Modules\Web\Containers\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Containers\Props\ContainerShowProps;
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
            'containers' => $this->deferFromHost(fn (): array => $lpod->containers($environment), live: $environment),
            'host' => $this->deferFromHost(fn () => $lpod->host($environment), live: $environment),
        ]);
    }

    public function create(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('operate', $environment);

        return Inertia::render('Containers/ContainerCreate', [
            'environment' => EnvironmentData::fromModel($environment),
            'applications' => $this->deferFromHost(fn (): array => $lpod->applications($environment)),
            'networks' => $this->deferFromHost(fn (): array => $lpod->networks($environment)),
            'secrets' => $this->deferFromHost(fn (): array => $lpod->secrets($environment)),
        ]);
    }

    public function show(Environment $environment, string $container, ContainerShowProps $props): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Containers/ContainerShow', $props->for($environment, $container));
    }
}
