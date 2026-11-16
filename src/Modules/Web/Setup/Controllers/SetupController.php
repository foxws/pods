<?php

declare(strict_types=1);

namespace Modules\Web\Setup\Controllers;

use Domain\Users\Actions\CreateUser;
use Domain\Users\Actions\OwnerSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Web\Setup\Requests\CreateOwnerRequest;

/**
 * Creates the owner of a new Pods instance. Gone once the owner exists.
 */
class SetupController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('guest'),
            new Middleware('throttle:10,1', only: ['store']),
        ];
    }

    public function show(OwnerSetup $setup): Response
    {
        abort_unless($setup->isNeeded(), 404);

        return Inertia::render('Auth/AuthSetup', [
            'action' => route('setup.store', absolute: false),
        ]);
    }

    public function store(CreateOwnerRequest $request, OwnerSetup $setup, CreateUser $createUser): RedirectResponse
    {
        // One owner, even when two people submit at the same moment.
        $owner = Cache::lock('pods:setup', 10)->block(5, function () use ($request, $setup, $createUser) {
            abort_unless($setup->isNeeded(), 404);

            $owner = $createUser($request->safe()->only(['name', 'email', 'password']), 'super-admin');

            $setup->forget();

            return $owner;
        });

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
