<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Modules\Web\Notifications\Controllers\NotificationBulkController;
use Modules\Web\Notifications\Controllers\NotificationController;

/**
 * @param  array<string, mixed>  $attributes
 */
function storeNotification(User $user, array $attributes = []): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'Domain\Volumes\Notifications\VolumeExportFinished',
        'data' => [
            'title' => 'immich-library is exported',
            'message' => 'The archive from Local is ready to download on the Volumes page.',
            'level' => 'success',
            'url' => '/environments/01example/volumes',
            'environment' => 'Local',
        ],
        ...$attributes,
    ]);
}

// index

it('lists only the notifications of the user, newest first', function () {
    $user = User::factory()->create();
    $older = storeNotification($user, ['created_at' => now()->subDay()]);
    $newer = storeNotification($user, ['read_at' => now()]);
    storeNotification(User::factory()->create());

    $this->actingAs($user)
        ->get(action([NotificationController::class, 'index']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/NotificationIndex')
            ->where('filter', 'all')
            ->where('notifications', 1)
            ->has('messages.data', 2)
            ->where('messages.data.0.id', $newer->id)
            ->where('messages.data.1.id', $older->id)
            ->where('messages.data.1.type', 'VolumeExportFinished')
            ->where('messages.data.1.title', 'immich-library is exported')
            ->where('messages.data.1.level', 'success')
            ->where('messages.data.1.url', '/environments/01example/volumes')
            ->where('messages.data.1.environment', 'Local')
            ->where('messages.data.1.read_at', null)
            ->missing('environment'));
});

it('only lists the unread notifications when asked', function () {
    $user = User::factory()->create();
    $unread = storeNotification($user);
    storeNotification($user, ['read_at' => now()]);

    $this->actingAs($user)
        ->get(action([NotificationController::class, 'index'], ['filter' => 'unread']))
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'unread')
            ->has('messages.data', 1)
            ->where('messages.data.0.id', $unread->id));
});

it('shows a notification without the stored shape as info', function () {
    $user = User::factory()->create();
    storeNotification($user, ['data' => ['level' => 'loud']]);

    $this->actingAs($user)
        ->get(action([NotificationController::class, 'index']))
        ->assertInertia(fn ($page) => $page
            ->where('messages.data.0.title', 'VolumeExportFinished')
            ->where('messages.data.0.level', 'info')
            ->where('messages.data.0.url', null));
});

it('lists the notifications inside the selected environment', function () {
    $environment = Environment::factory()->create();

    $this->actingAs(User::factory()->create()->assignRole('super-admin'))
        ->get(action([NotificationController::class, 'environment'], $environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/NotificationIndex')
            ->where('environment.id', $environment->ulid));
});

it('redirects guests away from the notifications', function () {
    User::factory()->create()->assignRole('super-admin'); // an instance with its owner, so guests see the login page

    $this->get(action([NotificationController::class, 'index']))
        ->assertRedirect(route('login'));
});

// update

it('marks a notification read and unread again', function () {
    $user = User::factory()->create();
    $notification = storeNotification($user);

    $this->actingAs($user)
        ->put(action([NotificationController::class, 'update'], $notification->id), ['read' => true])
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->put(action([NotificationController::class, 'update'], $notification->id), ['read' => false]);

    expect($notification->refresh()->read_at)->toBeNull();
});

it('does not touch the notifications of another user', function () {
    $notification = storeNotification(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->put(action([NotificationController::class, 'update'], $notification->id), ['read' => true])
        ->assertNotFound();

    // Someone else, with a session of their own
    $this->flushSession();
    $this->actingAs(User::factory()->create())
        ->delete(action([NotificationController::class, 'destroy'], $notification->id))
        ->assertNotFound();

    expect($notification->refresh())->read_at->toBeNull();
});

// destroy

it('removes a notification', function () {
    $user = User::factory()->create();
    $notification = storeNotification($user);

    $this->actingAs($user)
        ->delete(action([NotificationController::class, 'destroy'], $notification->id))
        ->assertRedirect();

    $this->assertModelMissing($notification);
});

// bulk

it('marks every notification of the user read', function () {
    $user = User::factory()->create();
    storeNotification($user);
    storeNotification($user);
    $other = storeNotification(User::factory()->create());

    $this->actingAs($user)
        ->put(action([NotificationBulkController::class, 'update']))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->refresh()->read_at)->toBeNull();
});

it('clears only the read notifications of the user', function () {
    $user = User::factory()->create();
    $unread = storeNotification($user);
    $read = storeNotification($user, ['read_at' => now()]);
    $otherRead = storeNotification(User::factory()->create(), ['read_at' => now()]);

    $this->actingAs($user)
        ->delete(action([NotificationBulkController::class, 'destroy']))
        ->assertRedirect();

    $this->assertModelMissing($read);
    $this->assertModelExists($unread);
    $this->assertModelExists($otherRead);
});
