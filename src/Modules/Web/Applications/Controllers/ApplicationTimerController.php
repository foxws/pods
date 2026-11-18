<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\ManifestTimerData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationTimerController implements HasMiddleware
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
     * Starts the container of one of the application's timers now, without waiting for its schedule.
     */
    public function store(Environment $environment, string $application, string $timer, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        abort_unless(preg_match(ManifestTimerData::NamePattern, $timer) === 1, 404);

        $this->toHost(
            fn () => $lpod->runTimer($environment, $application, $timer),
            success: __(':timer of :application started', ['timer' => $timer, 'application' => $application]),
            description: __('Follow it in the logs of :application.', ['application' => $application]),
            failure: __(':timer not started', ['timer' => $timer]),
        );

        return back();
    }
}
