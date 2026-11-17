<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

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
use Modules\Web\Environments\Requests\UpdateHostSettingsRequest;

class EnvironmentSettingsController implements HasMiddleware
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

    public function show(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Environments/EnvironmentSettings', [
            'environment' => EnvironmentData::fromModel($environment),
            'settings' => $this->deferFromHost(fn () => $lpod->hostSettings($environment)),
            'proxy' => $this->deferFromHost(fn () => $lpod->proxySettings($environment), 'proxy'),
        ]);
    }

    public function update(UpdateHostSettingsRequest $request, Environment $environment, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->updateHostSettings(
                $environment,
                autoUpdate: $request->boolean('auto_update'),
                schedule: $request->string('schedule')->toString(),
                rollback: $request->boolean('rollback'),
                linger: $request->boolean('linger'),
            ),
            success: __('Settings saved'),
            description: __('lpod updated the auto-update timer and lingering on :environment.', ['environment' => $environment->name]),
            failure: __('Settings not saved'),
        );

        return back();
    }
}
