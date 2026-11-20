<?php

declare(strict_types=1);

namespace Modules\Web\Notifications\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Domain\Users\DataObjects\NotificationData;
use Domain\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render($request, $user);
    }

    /**
     * The same list, with the environment that was selected, so the sidebar keeps its host.
     */
    public function environment(Request $request, #[CurrentUser] User $user, Environment $environment): Response
    {
        Gate::authorize('view', $environment);

        return $this->render($request, $user, $environment);
    }

    /**
     * Marks one notification read, or unread again.
     */
    public function update(Request $request, #[CurrentUser] User $user, string $notification): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate(['read' => ['required', 'boolean']]);

        $record = $user->notifications()->findOrFail($notification);

        $validated['read'] ? $record->markAsRead() : $record->markAsUnread();

        return back();
    }

    public function destroy(#[CurrentUser] User $user, string $notification): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->notifications()->findOrFail($notification)->delete();

        return back();
    }

    protected function render(Request $request, User $user, ?Environment $environment = null): Response
    {
        Gate::authorize('update', $user);

        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->simplePaginate(25)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): NotificationData => NotificationData::fromModel($notification));

        return Inertia::render('Notifications/NotificationIndex', array_filter([
            'filter' => $filter,
            'messages' => Inertia::scroll(fn () => $notifications),
            'environment' => $environment ? EnvironmentData::fromModel($environment) : null,
        ]));
    }
}
