<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\LinkApplicationSource;
use Domain\Applications\Exceptions\CannotLinkApplicationSourceException;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;
use Modules\Web\Quadlets\Requests\FetchQuadletsRequest;

class ApplicationSourceController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Links an application to the Git source its files came from, such as one installed outside
     * Pods, so it can be updated. The files on the host stay as they are.
     */
    public function update(
        FetchQuadletsRequest $request,
        Environment $environment,
        string $application,
        LinkApplicationSource $linkApplicationSource,
    ): RedirectResponse {
        Gate::authorize('operate', $environment);

        $reference = $request->string('reference')->toString();

        try {
            $installation = $linkApplicationSource(
                $environment,
                $application,
                $request->string('repository')->toString(),
                $reference,
                $request->string('path')->trim('/')->toString(),
            );
        } catch (CannotLinkApplicationSourceException|LpodException $exception) {
            toast(title: __(':application not linked', ['application' => $application]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(
            title: __(':application is linked to :reference', ['application' => $application, 'reference' => $reference]),
            description: $installation->isChangedOutsidePods()
                ? __('Some of its files differ from the source. Replace them with the source\'s, or keep them as they are.')
                : __('Its files match the source, so Pods can update it from there.'),
        );

        return back();
    }
}
