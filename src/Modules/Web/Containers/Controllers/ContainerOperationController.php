<?php

declare(strict_types=1);

namespace Modules\Web\Containers\Controllers;

use Domain\Containers\Enums\ContainerOperation;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\WritesToHost;

class ContainerOperationController implements HasMiddleware
{
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function __invoke(Environment $environment, string $container, ContainerOperation $operation, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->operateContainer($environment, $container, $operation),
            success: __(':operation requested', ['operation' => $operation->label()]),
            description: __('Podman is working on :container.', ['container' => $container]),
            failure: __(':operation failed', ['operation' => $operation->label()]),
        );

        return back();
    }
}
