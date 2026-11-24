<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Catalog\Actions\ListCatalog;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Domain\Users\DataObjects\PinData;
use Domain\Users\Enums\PinType;
use Domain\Users\Models\User;
use Foundation\Settings\ProxySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Props\ApplicationShowProps;
use Modules\Web\Applications\Requests\DestroyApplicationRequest;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationController implements HasMiddleware
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

    public function index(Request $request, Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Applications/ApplicationIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'pinned' => PinData::collect($user->pins()->ofType(PinType::Application)->on($environment)->orderBy('name')->get()),
            'applications' => $this->deferFromHost(fn (): array => $lpod->applications($environment), live: $environment),
            'host' => $this->deferFromHost(fn () => $lpod->host($environment), live: $environment),
            'images' => $this->deferFromHost(fn (): array => $lpod->images($environment), 'images'),
        ]);
    }

    public function show(Environment $environment, string $application, ApplicationShowProps $props): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Applications/ApplicationShow', $props->for($environment, $application));
    }

    public function create(Environment $environment, LpodClient $lpod, ListCatalog $listCatalog, ProxySettings $proxySettings): Response
    {
        Gate::authorize('operate', $environment);

        return Inertia::render('Applications/ApplicationCreate', [
            'environment' => EnvironmentData::fromModel($environment),
            'catalog' => $this->deferFromHost(fn () => $listCatalog($environment), 'catalog'),
            'proxy' => $this->deferFromHost(fn () => $lpod->proxyRoutes($environment), 'proxy'),
            'defaultDomain' => $proxySettings->domain,
            'secrets' => $this->deferFromHost(fn (): array => $lpod->secrets($environment)),
        ]);
    }

    public function destroy(DestroyApplicationRequest $request, Environment $environment, string $application, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $removed = $this->toHost(
            fn () => $lpod->removeApplication(
                $environment,
                $application,
                volumes: $request->boolean('volumes'),
                images: $request->boolean('images'),
                secrets: $request->boolean('secrets'),
            ),
            success: __(':application removed', ['application' => $application]),
            description: __('Its units are stopped and its Quadlet files are gone.'),
            failure: __(':application not removed', ['application' => $application]),
        );

        if (! $removed) {
            return back();
        }

        $environment->installations()->where('application', $application)->delete();
        $environment->pins()->ofType(PinType::Application)->where('name', $application)->delete();

        return to_action([self::class, 'index'], $environment);
    }
}
