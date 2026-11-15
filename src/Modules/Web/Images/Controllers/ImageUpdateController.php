<?php

declare(strict_types=1);

namespace Modules\Web\Images\Controllers;

use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\WritesToHost;

class ImageUpdateController implements HasMiddleware
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
     * Checks the registries for newer images with podman auto-update --dry-run.
     */
    public function __invoke(Environment $environment, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->checkForUpdates($environment),
            success: __('Checked for updates'),
            description: __('Images with a newer version are marked in the list.'),
            failure: __('Update check failed'),
        );

        return back();
    }
}
