<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Controllers;

use Domain\Applications\Actions\RunSetupStep;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\ManifestSetupStepData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Integrations\Lpod\Exceptions\LpodException;

class ApplicationSetupController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Runs a setup step from the application's stored pods.yml (its after_install), such as
     * migrating the database or creating the first admin, and flashes what the command printed.
     */
    public function store(Request $request, Environment $environment, string $application, string $step, RunSetupStep $runSetupStep): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $installation = $environment->installations()->where('application', $application)->firstOrFail();

        $setupStep = collect($installation->manifest()->after_install ?? [])
            ->first(fn (ManifestSetupStepData $candidate): bool => $candidate->name === $step);

        abort_if($setupStep === null, 404);

        /** @var array{inputs?: array<string, string>} $validated */
        $validated = $request->validate($setupStep->rules());

        try {
            $result = $runSetupStep($installation, $setupStep, $validated['inputs'] ?? []);
        } catch (LpodException $exception) {
            toast(title: __(':step not run', ['step' => $setupStep->label]), description: $exception->getMessage(), type: 'error');

            return back();
        }

        Inertia::flash('setup', [
            'step' => $setupStep->name,
            'exit_code' => $result->exit_code,
            'output' => trim($result->stdout."\n".$result->stderr),
        ]);

        if (! $result->successful()) {
            toast(
                title: __(':step failed', ['step' => $setupStep->label]),
                description: Str::of($result->stderr ?: $result->stdout)->trim()->explode("\n")->last() ?: __('It exited with code :code.', ['code' => $result->exit_code]),
                type: 'error',
            );

            return back();
        }

        toast(
            title: __(':step done', ['step' => $setupStep->label]),
            description: __('It ran in :container.', ['container' => $setupStep->container]),
        );

        return back();
    }
}
