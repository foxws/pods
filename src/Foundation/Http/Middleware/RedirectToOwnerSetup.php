<?php

declare(strict_types=1);

namespace Foundation\Http\Middleware;

use Closure;
use Domain\Users\Actions\OwnerSetup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends guests to the setup page while Pods has no owner, since there's no one to log in as.
 */
class RedirectToOwnerSetup
{
    public function __construct(
        protected OwnerSetup $setup,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user() !== null
            || $request->routeIs('setup.*')
            || $request->expectsJson()
            || ! $this->setup->isNeeded()
        ) {
            return $next($request);
        }

        return redirect()->route('setup.show');
    }
}
