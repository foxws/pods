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
use Modules\Web\Quadlets\Requests\QuadletFilesRequest;

class QuadletCheckController implements HasMiddleware
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
     * Checks Quadlet files with podman quadlet --dryrun, without installing them.
     */
    public function __invoke(QuadletFilesRequest $request, Environment $environment, LpodClient $lpod): JsonResponse
    {
        Gate::authorize('operate', $environment);

        return response()->json($this->fromHost(fn () => $lpod->checkQuadlets($environment, $request->files())));
    }
}
