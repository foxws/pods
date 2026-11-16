<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Modules\Web\Settings\Controllers\SettingsController;

it('opens the account section by default', function () {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'index']))
        ->assertRedirect(action([SettingsController::class, 'show'], 'account'));
});

it('shows a settings section', function (string $section) {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'show'], $section))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/SettingsShow')
            ->where('section', $section));
})->with(['account', 'security']);

it('only shows the general section to a super-admin', function () {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'show'], 'general'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create()->assignRole('super-admin'))
        ->get(action([SettingsController::class, 'show'], 'general'))
        ->assertSuccessful();
});

it('does not route unknown sections', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/registries')
        ->assertNotFound();
});

it('redirects guests to the login page', function () {
    User::factory()->create()->assignRole('super-admin'); // an instance with its owner, so guests see the login page

    $this->get(action([SettingsController::class, 'show'], 'account'))
        ->assertRedirect(route('login'));
});
