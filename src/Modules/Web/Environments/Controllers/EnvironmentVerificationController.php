<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Environments\Actions\VerifyEnvironment;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;

class EnvironmentVerificationController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Asks the host again for its lpod, Podman and systemd versions, which Pods otherwise only reads now and then.
     */
    public function store(Environment $environment, VerifyEnvironment $verifyEnvironment): RedirectResponse
    {
        Gate::authorize('view', $environment);

        try {
            $environment = $verifyEnvironment($environment);
        } catch (LpodException $exception) {
            toast(title: __(':environment not checked', ['environment' => $environment->name]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(
            title: __(':environment checked', ['environment' => $environment->name]),
            description: __('lpod :lpod, Podman :podman, systemd :systemd.', [
                'lpod' => $environment->facts['lpod'] ?? '?',
                'podman' => $environment->facts['podman'] ?? '?',
                'systemd' => $environment->facts['systemd'] ?? '?',
            ]),
        );

        return back();
    }
}
