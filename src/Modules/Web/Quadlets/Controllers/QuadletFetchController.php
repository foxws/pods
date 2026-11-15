<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Controllers;

use Domain\Environments\Models\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Quadlets\Requests\FetchQuadletsRequest;

class QuadletFetchController implements HasMiddleware
{
    use ReadsFromHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Fetches the Quadlet files of a Git repository on the host, so they can be reviewed before installing.
     */
    public function __invoke(FetchQuadletsRequest $request, Environment $environment, LpodClient $lpod): JsonResponse
    {
        Gate::authorize('operate', $environment);

        return response()->json($this->fromHost(fn () => $lpod->fetchQuadlets(
            $environment,
            $request->string('repository')->toString(),
            $request->string('reference')->toString(),
            $request->string('path')->trim('/')->toString(),
        )));
    }
}
