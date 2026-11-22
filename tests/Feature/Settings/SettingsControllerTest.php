<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
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

it('only shows the sections of the instance to a super-admin', function (string $section) {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'show'], $section))
        ->assertForbidden();

    // Someone else, with a session of their own
    $this->flushSession();
    $this->actingAs(User::factory()->create()->assignRole('super-admin'))
        ->get(action([SettingsController::class, 'show'], $section))
        ->assertSuccessful();
})->with(['general', 'proxy', 'catalog']);

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

it('shows a settings section inside the selected environment', function () {
    $environment = Environment::factory()->create();

    $this->actingAs(User::factory()->create()->assignRole('super-admin'))
        ->get(action([SettingsController::class, 'showInEnvironment'], [$environment, 'proxy']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/SettingsShow')
            ->where('section', 'proxy')
            ->where('environment.id', $environment->ulid));
});

it('leaves the environment out of the settings outside one', function () {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'show'], 'account'))
        ->assertInertia(fn ($page) => $page->missing('environment'));
});

it('forbids the settings inside an environment the user cannot view', function () {
    $this->actingAs(User::factory()->create())
        ->get(action([SettingsController::class, 'showInEnvironment'], [Environment::factory()->create(), 'account']))
        ->assertForbidden();
});
