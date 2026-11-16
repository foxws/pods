<?php

declare(strict_types=1);

namespace Modules\Web\Networks\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;

class NetworkController implements HasMiddleware
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

        return Inertia::render('Networks/NetworkIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'networks' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->networks($environment))),
            'containers' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->containers($environment)), 'containers'),
        ]);
    }

    public function destroy(Environment $environment, string $network, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->removeNetwork($environment, $network),
            success: __('Network removed'),
            description: __(':name was removed from Podman.', ['name' => $network]),
            failure: __('Network not removed'),
        );

        return back();
    }
}
