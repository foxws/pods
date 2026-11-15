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

class ImagePruneController implements HasMiddleware
{
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function __invoke(Environment $environment, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->pruneImages($environment),
            success: __('Unused images pruned'),
            description: __('Images that no container uses were removed.'),
            failure: __('Prune failed'),
        );

        return back();
    }
}
