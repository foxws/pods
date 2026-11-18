<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\ListApplicationUpdates;
use Domain\Applications\Actions\UpdateApplication;
use Domain\Applications\Exceptions\CannotUpdateApplicationException;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\GitTagData;
use Domain\Quadlets\DataObjects\ManifestSetupStepData;
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
     * Updates an application installed from Git to a newer tag of its repository, or to the newest
     * commit of the branch it was installed from. The "tag" is then that branch. An application with
     * files that differ from its source can also get its own reference again, to replace them.
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

            if ($installation->isChangedOutsidePods() && $installation->reference !== null) {
                $tags[] = $installation->reference;
            }

            $request->validate(
                ['tag' => ['required', 'string', Rule::in($tags)], 'remove_old_files' => ['boolean']],
                ['tag.in' => __('Pick one of the newer tags of the repository.')],
            );

            $oldFiles = $updateApplication($installation, $request->string('tag')->toString(), $request->boolean('remove_old_files', true));
        } catch (CannotUpdateApplicationException|LpodException $exception) {
            toast(title: __(':application not updated', ['application' => $application]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        $tag = $request->string('tag')->toString();
        $commit = $installation->refresh()->commit;
        $updateSteps = $installation->updated_from !== null ? ($installation->manifest()->after_update ?? []) : [];

        toast(
            title: GitTagData::versionOf($tag) === null && $commit !== null
                ? __(':application is at :branch (:commit)', ['application' => $application, 'branch' => $tag, 'commit' => $commit])
                : __(':application is at :tag', ['application' => $application, 'tag' => $tag]),
            description: collect([
                __('Its units are starting with the new files.'),
                $oldFiles['removed'] !== [] ? __('Removed what :tag no longer has: :files.', ['tag' => $tag, 'files' => implode(', ', $oldFiles['removed'])]) : null,
                $oldFiles['kept'] !== [] ? __('These files aren\'t in :tag and stay on the host: :files.', ['tag' => $tag, 'files' => implode(', ', $oldFiles['kept'])]) : null,
                $updateSteps !== []
                    ? __('Finish the update on its page: :steps.', ['steps' => implode(', ', array_map(fn (ManifestSetupStepData $step): string => $step->label, $updateSteps))])
                    : __('Run its setup steps again if the release needs it.'),
            ])->filter()->implode(' '),
        );

        return back();
    }
}
