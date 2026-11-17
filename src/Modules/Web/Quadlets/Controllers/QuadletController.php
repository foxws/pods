<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Quadlets\Actions\InstallQuadlets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Environments\Concerns\WritesToHost;
use Modules\Web\Quadlets\Requests\InstallQuadletsRequest;

class QuadletController implements HasMiddleware
{
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function store(InstallQuadletsRequest $request, Environment $environment, InstallQuadlets $installQuadlets): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $application = $request->string('application')->toString();
        $start = $request->boolean('start', true);

        $installed = $this->toHost(
            fn () => $installQuadlets(
                environment: $environment,
                application: $application,
                files: $request->files(),
                secrets: $request->secrets(),
                start: $start,
                replace: $request->boolean('replace'),
                source: $request->source(),
                edit: $request->boolean('edit'),
            ),
            success: __(':application installed', ['application' => $application]),
            description: $start ? __('systemd is starting its units.') : __('Its units are ready to start.'),
            failure: __(':application not installed', ['application' => $application]),
        );

        if (! $installed) {
            return back();
        }

        return to_action([ApplicationController::class, 'show'], [$environment, $application]);
    }
}
