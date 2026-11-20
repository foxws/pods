<?php

declare(strict_types=1);

namespace Modules\Web\Volumes\Controllers;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Domain\Volumes\Actions\QueueVolumeExports;
use Domain\Volumes\DataObjects\HostVolumeData;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
use Modules\Web\Volumes\Requests\StoreVolumeExportRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolumeExportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth', except: ['download']),
            new Middleware('verified', except: ['download']),
            // A download link works on its own, like the one sent when an export is ready.
            new Middleware('signed', only: ['download']),
        ];
    }

    public function store(StoreVolumeExportRequest $request, #[CurrentUser] User $user, Environment $environment, LpodClient $lpod, QueueVolumeExports $queueVolumeExports): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $application = $request->string('application')->toString();

        try {
            $volumes = $application !== '' ? $this->volumesOf($environment, $application, $lpod) : array_values($request->array('volumes'));
        } catch (LpodException $exception) {
            toast(title: __('Export not started'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        if ($volumes === []) {
            toast(title: __('Nothing to export'), description: __(':name has no volumes in Podman\'s storage.', ['name' => $application]), type: 'warning');

            return back();
        }

        $exports = $queueVolumeExports($environment, $user, $volumes, $request->boolean('stop_applications'));

        if ($exports->isEmpty()) {
            toast(title: __('Already exporting'), description: __('An export of that volume is already waiting or running.'), type: 'info');

            return back();
        }

        toast(
            title: __('Export started'),
            description: trans_choice(':volumes is exported in the background. You\'ll find the archive here when it\'s ready.|:volumes are exported in the background, one after another. You\'ll find the archives here when they\'re ready.', $exports->count(), [
                'volumes' => $exports->pluck('volume')->join(', ', __(' and ')),
            ]),
        );

        return back();
    }

    public function download(Environment $environment, VolumeExport $volumeExport): StreamedResponse
    {
        $path = $volumeExport->isDownloadable() ? $volumeExport->path : null;

        abort_if($path === null || ! VolumeExport::disk()->exists($path), 404);

        return VolumeExport::disk()->download($path, $volumeExport->fileName(), [
            'Content-Type' => 'application/x-tar',
        ]);
    }

    public function destroy(Environment $environment, VolumeExport $volumeExport): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        abort_unless($volumeExport->status->isFinished(), 409, __('The export is still running.'));

        $volumeExport->delete();

        toast(title: __('Export removed'), description: __('The archive of :name was deleted.', ['name' => $volumeExport->volume]));

        return back();
    }

    /**
     * @return list<string>
     */
    protected function volumesOf(Environment $environment, string $application, LpodClient $lpod): array
    {
        $volumes = array_filter(
            $lpod->volumes($environment),
            fn (HostVolumeData $volume): bool => $volume->isExportable() && in_array($application, $volume->applications, true),
        );

        return array_values(array_map(fn (HostVolumeData $volume): string => $volume->name, $volumes));
    }
}
