<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\RemoveDropIn;
use Domain\Applications\Actions\WriteDropIn;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Requests\DropInRequest;
use Modules\Web\Applications\Requests\WriteDropInRequest;
use Modules\Web\Environments\Concerns\WritesToHost;

class ApplicationDropInController implements HasMiddleware
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
     * Adds or replaces a drop-in, and restarts the application when asked, so it applies.
     */
    public function update(WriteDropInRequest $request, Environment $environment, string $application, WriteDropIn $writeDropIn, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $name = $request->string('name')->toString();

        $written = $this->toHost(
            fn () => $writeDropIn($environment, $application, $name, $request->string('content')->toString()),
            success: __(':name saved', ['name' => $name]),
            description: __(':application picks it up when it restarts.', ['application' => $application]),
            failure: __(':name not saved', ['name' => $name]),
        );

        if ($written && $request->boolean('restart')) {
            $this->restart($lpod, $environment, $application, $name, __(':name saved', ['name' => $name]));
        }

        return back();
    }

    /**
     * Removes a drop-in, and restarts the application when asked, so its units run without it.
     */
    public function destroy(DropInRequest $request, Environment $environment, string $application, RemoveDropIn $removeDropIn, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $name = $request->string('name')->toString();

        $removed = $this->toHost(
            fn () => $removeDropIn($environment, $application, $name),
            success: __(':name removed', ['name' => $name]),
            description: __(':application runs without it once it restarts.', ['application' => $application]),
            failure: __(':name not removed', ['name' => $name]),
        );

        if ($removed && $request->boolean('restart')) {
            $this->restart($lpod, $environment, $application, $name, __(':name removed', ['name' => $name]));
        }

        return back();
    }

    /**
     * The change is made by now, so a failed restart says so rather than that it wasn't.
     */
    protected function restart(LpodClient $lpod, Environment $environment, string $application, string $name, string $success): void
    {
        $this->toHost(
            fn () => $lpod->restart($environment, $application),
            success: $success,
            description: __('systemd is restarting :application.', ['application' => $application]),
            failure: __(':name changed, but :application didn\'t restart', ['name' => $name, 'application' => $application]),
        );
    }
}
