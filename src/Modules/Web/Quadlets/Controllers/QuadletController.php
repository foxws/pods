<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Controllers;

use Domain\Applications\Actions\ApplyManifestTimers;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\Actions\InstallQuadlets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
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

    public function store(
        InstallQuadletsRequest $request,
        Environment $environment,
        InstallQuadlets $installQuadlets,
        ApplyManifestTimers $applyManifestTimers,
        LpodClient $lpod,
    ): RedirectResponse {
        Gate::authorize('operate', $environment);

        $application = $request->string('application')->toString();
        $start = $request->boolean('start', true);
        $edit = $request->boolean('edit');
        $previousManifest = $environment->installations()->firstWhere('application', $application)?->manifest();

        $installed = $this->toHost(
            fn () => $installQuadlets(
                environment: $environment,
                application: $application,
                files: $request->files(),
                secrets: $request->secrets(),
                start: $start,
                replace: $request->boolean('replace'),
                source: $request->source(),
                edit: $edit,
            ),
            success: $edit ? __(':application saved', ['application' => $application]) : __(':application installed', ['application' => $application]),
            description: match (true) {
                $edit => __(':application picks the change up when it restarts.', ['application' => $application]),
                $start => __('systemd is starting its units.'),
                default => __('Its units are ready to start.'),
            },
            failure: $edit ? __(':application not saved', ['application' => $application]) : __(':application not installed', ['application' => $application]),
        );

        if (! $installed) {
            return back();
        }

        // The edit is saved by now, so a failed restart says so rather than that it wasn't.
        if ($edit && $request->boolean('restart')) {
            $this->toHost(
                fn () => $lpod->restart($environment, $application),
                success: __(':application saved', ['application' => $application]),
                description: __('systemd is restarting :application.', ['application' => $application]),
                failure: __(':application saved, but it didn\'t restart', ['application' => $application]),
            );
        }

        if (! $edit) {
            try {
                $applyManifestTimers($environment, $application, $environment->installations()->firstWhere('application', $application)?->manifest(), $previousManifest);
            } catch (LpodException $exception) {
                toast(title: __(':application installed, but its timers weren\'t set up', ['application' => $application]), description: $exception->getMessage(), type: 'error');
            }
        }

        $routes = $request->routes();

        // The application is installed either way, so a routes failure doesn't send the owner back.
        if ($routes !== []) {
            $this->toHost(
                fn () => $lpod->setRoutes($environment, $application, $routes),
                success: __(':application installed', ['application' => $application]),
                description: __('systemd is starting its units, and the proxy sends :hosts to it.', ['hosts' => implode(', ', array_column($routes, 'host'))]),
                failure: __(':application installed, but its routes weren\'t saved', ['application' => $application]),
            );
        }

        return to_action([ApplicationController::class, 'show'], [$environment, $application]);
    }
}
