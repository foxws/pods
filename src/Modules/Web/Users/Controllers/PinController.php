<?php

declare(strict_types=1);

namespace Modules\Web\Users\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Users\Requests\StorePinRequest;

/**
 * Pins something on a host for the current user, such as an application to the overview, or unpins it.
 * Pins are the user's own and never touch the host.
 */
class PinController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function store(StorePinRequest $request, Environment $environment, #[CurrentUser] User $user): RedirectResponse
    {
        $user->pins()->firstOrCreate([
            'environment_id' => $environment->getKey(),
            'type' => $request->pinType(),
            'name' => $request->string('name')->toString(),
        ]);

        return back();
    }

    public function destroy(Environment $environment, Pin $pin): RedirectResponse
    {
        Gate::authorize('view', $environment);
        Gate::authorize('delete', $pin);

        $pin->delete();

        return back();
    }
}
