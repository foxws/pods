<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Requests\UpdateApplicationRoutesRequest;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationRoutesController implements HasMiddleware
{
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Replaces the hostnames the Pods proxy sends to the application. No routes removes them.
     */
    public function update(UpdateApplicationRoutesRequest $request, Environment $environment, string $application, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $routes = $request->routes();

        $this->toHost(
            fn () => $lpod->setRoutes($environment, $application, $routes),
            success: $routes === []
                ? __(':application has no routes', ['application' => $application])
                : __('Routes of :application saved', ['application' => $application]),
            description: $routes === []
                ? __('The proxy no longer sends any hostname to it.')
                : __('The proxy sends :hosts to it.', ['hosts' => implode(', ', array_column($routes, 'host'))]),
            failure: __('Routes not saved'),
        );

        return back();
    }
}
