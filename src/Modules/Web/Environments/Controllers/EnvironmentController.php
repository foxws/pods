<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Environments\Actions\CreateEnvironment;
use Domain\Environments\Actions\VerifyEnvironment;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Ssh\Exceptions\SshException;
use Integrations\Ssh\HostKeyScanner;
use Integrations\Ssh\SshIdentity;
use Modules\Web\Environments\Requests\StoreEnvironmentRequest;

class EnvironmentController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(SshIdentity $identity, HostKeyScanner $scanner): Response
    {
        Gate::authorize('viewAny', Environment::class);

        $environments = Environment::query()->orderByDesc('is_local')->orderBy('name')->get();

        return Inertia::render('Environments/EnvironmentIndex', [
            'environments' => $environments->map(fn (Environment $environment): array => [
                ...EnvironmentData::fromModel($environment)->toArray(),
                'fingerprint' => $scanner->fingerprint($environment->host_key),
            ]),
            'publicKey' => $identity->publicKey(),
        ]);
    }

    public function store(
        StoreEnvironmentRequest $request,
        CreateEnvironment $createEnvironment,
        VerifyEnvironment $verifyEnvironment,
    ): RedirectResponse {
        Gate::authorize('create', Environment::class);

        try {
            $environment = $createEnvironment(
                name: $request->string('name')->toString(),
                host: $request->string('host')->toString(),
                port: $request->integer('port'),
                user: $request->string('user')->toString(),
            );
        } catch (SshException $exception) {
            throw ValidationException::withMessages(['host' => $exception->getMessage()]);
        }

        try {
            $verifyEnvironment($environment);
        } catch (LpodException $exception) {
            toast(title: __('Environment added'), description: __('Pods could not connect yet: :error', ['error' => $exception->getMessage()]), type: 'warning');

            return to_action([self::class, 'index']);
        }

        toast(title: __('Environment added'), description: __(':name is connected.', ['name' => $environment->name]));

        return to_action(EnvironmentOverviewController::class, $environment);
    }

    public function destroy(Environment $environment): RedirectResponse
    {
        Gate::authorize('delete', $environment);

        $environment->delete();

        toast(title: __('Environment removed'), description: __(':name was removed from Pods. Nothing changed on the host.', ['name' => $environment->name]));

        return to_action([self::class, 'index']);
    }
}
