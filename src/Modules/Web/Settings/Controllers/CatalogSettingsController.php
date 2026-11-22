<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Controllers;

use Foundation\Settings\CatalogSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Settings\Requests\CatalogSettingsRequest;

/**
 * The repositories of the application catalog.
 */
class CatalogSettingsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function show(CatalogSettings $settings): JsonResponse
    {
        Gate::authorize('manage-application-settings');

        return response()->json($settings->toArray());
    }

    public function update(CatalogSettingsRequest $request, CatalogSettings $settings): RedirectResponse
    {
        $settings->repositories = $request->repositories();
        $settings->save();

        toast(title: __('Catalog saved'), description: __('Install application offers the applications of these repositories.'));

        return back();
    }
}
