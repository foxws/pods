<?php

declare(strict_types=1);

namespace Modules\Web\Notifications\Controllers;

use Domain\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

/**
 * Acts on all of the user's notifications at once: marks them read, or clears the read ones.
 */
class NotificationBulkController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function update(#[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->unreadNotifications()->update(['read_at' => now()]);

        toast(title: __('All caught up'), description: __('Every notification is marked as read.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $count = $user->readNotifications()->delete();

        toast(title: __('Notifications cleared'), description: trans_choice('{0} There were no read notifications.|{1} One read notification is removed.|[2,*] :count read notifications are removed.', $count));

        return back();
    }
}
