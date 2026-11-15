<?php

declare(strict_types=1);

namespace Modules\Web\Secrets\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Domain\Secrets\Actions\CreateSecret;
use Domain\Secrets\Actions\ReplaceSecret;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;
use Modules\Web\Secrets\Requests\StoreSecretRequest;
use Modules\Web\Secrets\Requests\UpdateSecretRequest;

class SecretController implements HasMiddleware
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

        return Inertia::render('Secrets/SecretIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'secrets' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->secrets($environment))),
            'applications' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->applications($environment))),
            'prefill' => [
                'name' => $this->queryName($request, 'secret'),
                'application' => $this->queryName($request, 'application'),
            ],
        ]);
    }

    public function store(StoreSecretRequest $request, Environment $environment, CreateSecret $createSecret): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $name = $request->string('name')->toString();

        try {
            $createSecret(
                environment: $environment,
                name: $name,
                driver: $request->string('driver')->toString(),
                value: $request->string('value')->toString(),
                attachments: $request->attachments(),
                restart: $request->boolean('restart'),
            );
        } catch (LpodException $exception) {
            toast(title: __('Secret not created'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(title: __('Secret created'), description: __(':name is stored in Podman.', ['name' => $name]));

        return to_action([self::class, 'index'], $environment);
    }

    public function update(UpdateSecretRequest $request, Environment $environment, string $secret, ReplaceSecret $replaceSecret): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        /** @var list<string> $restart */
        $restart = array_values($request->validated('restart', []));

        $this->toHost(
            fn () => $replaceSecret(
                environment: $environment,
                name: $secret,
                driver: $request->string('driver')->toString(),
                value: $request->string('value')->toString(),
                restart: $restart,
            ),
            success: __('Secret replaced'),
            description: __(':name has a new value.', ['name' => $secret]),
            failure: __('Secret not replaced'),
        );

        return back();
    }

    public function destroy(Environment $environment, string $secret, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->deleteSecret($environment, $secret),
            success: __('Secret deleted'),
            description: __(':name was removed from Podman.', ['name' => $secret]),
            failure: __('Secret not deleted'),
        );

        return back();
    }

    protected function queryName(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && preg_match(StoreSecretRequest::NamePattern, $value) === 1 ? $value : null;
    }
}
