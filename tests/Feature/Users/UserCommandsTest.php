<?php

declare(strict_types=1);

use Domain\Users\Actions\OwnerSetup;
use Domain\Users\Models\User;
use Foundation\Settings\GeneralSettings;

use function Pest\Laravel\artisan;

it('creates a verified super-admin while registration is closed', function () {
    expect(app(GeneralSettings::class)->allow_registration)->toBeFalse();

    artisan('users:create', ['--super-admin' => true, '--name' => 'Jane Owner', '--email' => 'jane@example.com'])
        ->expectsQuestion('Password', 'Sup3r-Secret-Passw0rd!')
        ->assertSuccessful();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('prints the setup code while there is no owner', function () {
    artisan('pods:setup', ['--code' => true])
        ->expectsOutput(app(OwnerSetup::class)->code())
        ->assertSuccessful();
});

it('prints nothing to set up once the owner exists', function () {
    User::factory()->create()->assignRole('super-admin');

    artisan('pods:setup', ['--code' => true])
        ->doesntExpectOutputToContain('-')
        ->assertSuccessful();
});
