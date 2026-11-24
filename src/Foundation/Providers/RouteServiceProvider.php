<?php

declare(strict_types=1);

namespace Foundation\Providers;

use Domain\Shared\Enums\HostResource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimits();
        $this->configurePatterns();
    }

    /**
     * Names are passed to lpod on the host, so only plain names match: never an option or a path.
     */
    protected function configurePatterns(): void
    {
        foreach (HostResource::cases() as $resource) {
            Route::pattern($resource->value, $resource->pattern());
        }
    }

    protected function configureRateLimits(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->getKey())
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}
