<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Environments\Actions\UpdateLpod;
use Domain\Environments\Exceptions\CannotUpdateLpodException;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;

class EnvironmentLpodController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Updates lpod on the host to the version this Pods needs.
     */
    public function update(Environment $environment, UpdateLpod $updateLpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        try {
            $environment = $updateLpod($environment);
        } catch (CannotUpdateLpodException|LpodException $exception) {
            toast(title: __('lpod not updated'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(
            title: __('lpod :version installed', ['version' => $environment->facts['lpod'] ?? '']),
            description: __('For :user on :environment.', ['user' => $environment->user, 'environment' => $environment->name]),
        );

        return back();
    }
}
