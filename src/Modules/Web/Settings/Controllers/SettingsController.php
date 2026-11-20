<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController implements HasMiddleware
{
    /**
     * @var list<string>
     */
    public const array Sections = ['account', 'security', 'general', 'proxy'];

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(): RedirectResponse
    {
        return to_action([self::class, 'show'], 'account');
    }

    public function show(string $section): Response
    {
        return $this->render($section);
    }

    /**
     * The same sections, with the environment that was selected, so the sidebar keeps its host.
     */
    public function showInEnvironment(Environment $environment, string $section): Response
    {
        Gate::authorize('view', $environment);

        return $this->render($section, $environment);
    }

    protected function render(string $section, ?Environment $environment = null): Response
    {
        if (in_array($section, ['general', 'proxy'], true)) {
            Gate::authorize('manage-application-settings');
        }

        return Inertia::render('Settings/SettingsShow', array_filter([
            'section' => $section,
            'environment' => $environment ? EnvironmentData::fromModel($environment) : null,
        ]));
    }
}
