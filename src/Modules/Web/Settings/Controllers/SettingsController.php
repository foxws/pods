<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(): RedirectResponse
    {
        return to_action([self::class, 'show'], 'account');
    }

    public function show(string $section): Response
    {
        if ($section === 'general') {
            Gate::authorize('manage-application-settings');
        }

        return Inertia::render('Settings/SettingsShow', [
            'section' => $section,
        ]);
    }
}
