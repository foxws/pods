<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\AcceptInstalledFiles;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationInstallationController implements HasMiddleware
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
     * Keeps the application's files as they are on the host now, so they're no longer
     * reported as changed outside Pods. The files on the host don't change.
     */
    public function update(Environment $environment, string $application, AcceptInstalledFiles $acceptInstalledFiles): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $acceptInstalledFiles($environment, $application),
            success: __('Pods tracks :application\'s files', ['application' => $application]),
            description: __('Changes made outside Pods from now on show up here.'),
            failure: __('Files not tracked'),
        );

        return back();
    }
}
