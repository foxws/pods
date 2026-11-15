<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\RunApplicationOperation;
use Domain\Applications\Enums\ApplicationOperation;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;

class ApplicationOperationController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function __invoke(
        Environment $environment,
        string $application,
        ApplicationOperation $operation,
        RunApplicationOperation $runOperation,
    ): RedirectResponse {
        Gate::authorize('operate', $environment);

        try {
            $runOperation($environment, $application, $operation);
        } catch (LpodException $exception) {
            toast(title: __(':operation failed', ['operation' => $operation->label()]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(title: __(':operation requested', ['operation' => $operation->label()]), description: __('systemd is working on :application.', ['application' => $application]));

        return back();
    }
}
