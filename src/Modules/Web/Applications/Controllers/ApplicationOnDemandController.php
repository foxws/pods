<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Requests\UpdateApplicationOnDemandRequest;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationOnDemandController implements HasMiddleware
{
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function update(UpdateApplicationOnDemandRequest $request, Environment $environment, string $application, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->enableOnDemand($environment, $application, $request->settings()),
            success: __(':application is on demand', ['application' => $application]),
            description: __('It sleeps now, and starts on the next request to port :port.', ['port' => $request->integer('port')]),
            failure: __('On demand not turned on'),
        );

        return back();
    }

    public function destroy(Environment $environment, string $application, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->disableOnDemand($environment, $application),
            success: __(':application runs all the time again', ['application' => $application]),
            description: __('Its on-demand units are gone, and it starts again, at boot too.'),
            failure: __('On demand not turned off'),
        );

        return back();
    }
}
