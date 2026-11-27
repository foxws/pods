<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Controllers;

use Foundation\Settings\ProxySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Settings\Requests\ProxyDefaultsRequest;

/**
 * The proxy settings a host's Proxy section starts from, and the domain hostnames are suggested under.
 */
class ProxyDefaultsController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function show(ProxySettings $settings): JsonResponse
    {
        Gate::authorize('manage-application-settings');

        return response()->json($settings->toArray());
    }

    public function update(ProxyDefaultsRequest $request, ProxySettings $settings): RedirectResponse
    {
        $settings->fill($request->defaults());
        $settings->save();

        toast(title: __('Proxy defaults saved'), description: __('Environments without a proxy start from them, and hostnames are suggested under the domain.'));

        return back();
    }
}
