<?php

declare(strict_types=1);

namespace Modules\Web\Images\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;
use Modules\Web\Environments\Concerns\WritesToHost;
use Modules\Web\Images\Requests\PullImageRequest;

class ImageController implements HasMiddleware
{
    use ReadsFromHost;
    use WritesToHost;

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        return Inertia::render('Images/ImageIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'images' => $this->deferFromHost(fn (): array => $lpod->images($environment), live: $this->changesTo($environment, 'images')),
        ]);
    }

    public function store(PullImageRequest $request, Environment $environment, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $reference = $request->string('reference')->toString();

        $this->toHost(
            fn () => $lpod->pullImage($environment, $reference),
            success: __('Image pulled'),
            description: __(':reference is in the image store.', ['reference' => $reference]),
            failure: __('Pull failed'),
        );

        return back();
    }

    public function destroy(Environment $environment, string $image, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        $this->toHost(
            fn () => $lpod->removeImage($environment, $image),
            success: __('Image removed'),
            description: __(':image is no longer in the image store.', ['image' => $image]),
            failure: __('Image not removed'),
        );

        return back();
    }
}
