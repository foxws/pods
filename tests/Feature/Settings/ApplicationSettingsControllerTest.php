<?php

declare(strict_types=1);

use Domain\Shared\Enums\Locale;
use Domain\Users\Models\User;
use Foundation\Settings\GeneralSettings;
use Modules\Web\Settings\Controllers\ApplicationSettingsController;

it('allows a super-admin to fetch application settings', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get(action([ApplicationSettingsController::class, 'show']));

    $response->assertOk();
    $response->assertJson([
        ...app(GeneralSettings::class)->toArray(),
        'default_locale' => Locale::EnUs->value,
    ]);
});

it('denies a regular user from fetching application settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(action([ApplicationSettingsController::class, 'show']));

    $response->assertForbidden();
});

it('allows a super-admin to update application settings', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->patch(action([ApplicationSettingsController::class, 'update']), [
        'site_name' => 'Boilerplate',
        'allow_registration' => true,
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('type', 'success');

    $settings = app(GeneralSettings::class);

    expect($settings->site_name)->toBe('Boilerplate')
        ->and($settings->allow_registration)->toBeTrue()
        ->and($settings->timezone)->toBe('UTC');
});

it('denies a regular user from updating application settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(action([ApplicationSettingsController::class, 'update']), [
        'site_name' => 'Boilerplate',
    ]);

    $response->assertForbidden();
});

it('saves how often host pages refresh', function () {
    $user = User::factory()->create()->assignRole('super-admin');

    $this->actingAs($user)
        ->patch(action([ApplicationSettingsController::class, 'update']), ['host_refresh' => 0])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(app(GeneralSettings::class)->host_refresh)->toBe(0);
});

it('refuses a refresh interval that is not one of the choices', function (mixed $interval) {
    $user = User::factory()->create()->assignRole('super-admin');

    $this->actingAs($user)
        ->patch(action([ApplicationSettingsController::class, 'update']), ['host_refresh' => $interval])
        ->assertSessionHasErrors('host_refresh');

    expect(app(GeneralSettings::class)->host_refresh)->toBe(10);
})->with([1, 45, -30, 'often']);

it('moves a host refresh still on the old default of 30 seconds to 10, and keeps one that was chosen', function (int $stored, int $migrated) {
    $settings = app(GeneralSettings::class);
    $settings->host_refresh = $stored;
    $settings->save();

    (require database_path('settings/2026_11_20_102951_shorten_host_refresh_to_ten_seconds.php'))->up();

    expect(app(GeneralSettings::class)->refresh()->host_refresh)->toBe($migrated);
})->with([
    'old default' => [30, 10],
    'chosen' => [180, 180],
    'off' => [0, 0],
]);
