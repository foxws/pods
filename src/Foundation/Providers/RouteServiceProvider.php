<?php

declare(strict_types=1);

namespace Foundation\Providers;

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
     * Application names are passed to lpod on the host, so only plain unit names match.
     */
    protected function configurePatterns(): void
    {
        Route::pattern('application', '[A-Za-z0-9][A-Za-z0-9_.@-]{0,127}');
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
