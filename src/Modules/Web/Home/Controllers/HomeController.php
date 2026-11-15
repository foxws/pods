<?php

declare(strict_types=1);

namespace Modules\Web\Home\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Environments\Controllers\EnvironmentController;

class HomeController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    /**
     * Opens the local environment, or the first one, or the list to add one.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $environment = $user->can('viewAny', Environment::class)
            ? Environment::query()->orderByDesc('is_local')->orderBy('name')->first()
            : null;

        if ($environment === null) {
            return to_action([EnvironmentController::class, 'index']);
        }

        return to_action([ApplicationController::class, 'index'], $environment);
    }
}
