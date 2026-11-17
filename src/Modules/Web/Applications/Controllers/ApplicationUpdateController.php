<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\ListApplicationUpdates;
use Domain\Applications\Actions\UpdateApplication;
use Domain\Applications\Exceptions\CannotUpdateApplicationException;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\GitTagData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Integrations\Lpod\Exceptions\LpodException;

class ApplicationUpdateController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Updates an application installed from Git to a newer tag of its repository.
     */
    public function store(
        Request $request,
        Environment $environment,
        string $application,
        ListApplicationUpdates $listApplicationUpdates,
        UpdateApplication $updateApplication,
    ): RedirectResponse {
        Gate::authorize('operate', $environment);

        $installation = $environment->installations()->where('application', $application)->firstOrFail();

        try {
            $tags = array_map(fn (GitTagData $tag): string => $tag->name, $listApplicationUpdates($installation));

            $request->validate(
                ['tag' => ['required', 'string', Rule::in($tags)]],
                ['tag.in' => __('Pick one of the newer tags of the repository.')],
            );

            $leftOver = $updateApplication($installation, $request->string('tag')->toString());
        } catch (CannotUpdateApplicationException|LpodException $exception) {
            toast(title: __(':application not updated', ['application' => $application]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(
            title: __(':application is at :tag', ['application' => $application, 'tag' => $request->string('tag')->toString()]),
            description: $leftOver === []
                ? __('Its units are starting with the new files. Run its setup steps again if the release needs it.')
                : __('Its units are starting with the new files. These files aren\'t in :tag and stay on the host: :files.', [
                    'tag' => $request->string('tag')->toString(),
                    'files' => implode(', ', $leftOver),
                ]),
        );

        return back();
    }
}
