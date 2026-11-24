<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

/**
 * Pins an application to its host's overview for the current user, or unpins it.
 * Pins are the user's own and never touch the host.
 */
class ApplicationPinController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function update(Request $request, Environment $environment, string $application): RedirectResponse
    {
        Gate::authorize('view', $environment);

        $this->user($request)->pinnedApplications()->firstOrCreate([
            'environment_id' => $environment->getKey(),
            'application' => $application,
        ]);

        return back();
    }

    public function destroy(Request $request, Environment $environment, string $application): RedirectResponse
    {
        Gate::authorize('view', $environment);

        $this->user($request)->pinnedApplications()
            ->where('environment_id', $environment->getKey())
            ->where('application', $application)
            ->delete();

        return back();
    }

    protected function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
