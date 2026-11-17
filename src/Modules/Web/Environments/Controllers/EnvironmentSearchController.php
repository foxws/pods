<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Applications\DataObjects\ApplicationData;
use Domain\Containers\DataObjects\HostContainerData;
use Domain\Environments\DataObjects\SearchResultData;
use Domain\Environments\Models\Environment;
use Domain\Images\DataObjects\ImageData;
use Domain\Networks\DataObjects\NetworkData;
use Domain\Pods\DataObjects\PodData;
use Domain\Secrets\DataObjects\SecretData;
use Domain\Volumes\DataObjects\HostVolumeData;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Integrations\Lpod\LpodClient;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Containers\Controllers\ContainerController;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Images\Controllers\ImageController;
use Modules\Web\Networks\Controllers\NetworkController;
use Modules\Web\Pods\Controllers\PodController;
use Modules\Web\Secrets\Controllers\SecretController;
use Modules\Web\Volumes\Controllers\VolumeController;

class EnvironmentSearchController implements HasMiddleware
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
     * Lists what the search palette can find on the host, from the same cached reads as the pages.
     */
    public function __invoke(Environment $environment, LpodClient $lpod): JsonResponse
    {
        Gate::authorize('view', $environment);

        return response()->json($this->fromHost(fn (): array => [
            ...array_map(fn (ApplicationData $application) => new SearchResultData(
                group: 'applications',
                label: $application->name,
                description: $application->description ?? $application->image,
                url: action([ApplicationController::class, 'show'], [$environment, $application->name]),
            ), $lpod->applications($environment)),
            ...array_map(fn (HostContainerData $container) => new SearchResultData(
                group: 'containers',
                label: $container->name,
                description: $container->image,
                url: action([ContainerController::class, 'show'], [$environment, $container->name]),
            ), $lpod->containers($environment)),
            ...array_map(fn (PodData $pod) => $this->filtered('pods', $pod->name, $pod->application, action([PodController::class, 'index'], $environment)), $lpod->pods($environment)),
            ...array_map(fn (ImageData $image) => $this->filtered(
                'images',
                $image->repository ? "{$image->repository}:{$image->tag}" : substr($image->id, 0, 12),
                implode(', ', $image->used_by) ?: null,
                action([ImageController::class, 'index'], $environment),
            ), $lpod->images($environment)),
            ...array_map(fn (HostVolumeData $volume) => $this->filtered('volumes', $volume->name, implode(', ', $volume->applications) ?: null, action([VolumeController::class, 'index'], $environment)), $lpod->volumes($environment)),
            ...array_map(fn (NetworkData $network) => $this->filtered('networks', $network->name, $network->subnet, action([NetworkController::class, 'index'], $environment)), $lpod->networks($environment)),
            ...array_map(fn (SecretData $secret) => $this->filtered('secrets', $secret->name, implode(', ', $secret->used_by) ?: null, action([SecretController::class, 'index'], $environment)), $lpod->secrets($environment)),
        ]));
    }

    /**
     * Links to a list page with its search field already holding the label, for resources without a page of their own.
     */
    protected function filtered(string $group, string $label, ?string $description, string $index): SearchResultData
    {
        return new SearchResultData(
            group: $group,
            label: $label,
            description: $description,
            url: $index.'?'.http_build_query(['search' => $label]),
        );
    }
}
