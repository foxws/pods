<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Domain\Users\Models\User;
use Spatie\Permission\Models\Role;

it('only seeds the roles outside local development', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(Role::query()->pluck('name')->all())->toContain('admin', 'super-admin')
        ->and(User::query()->where('email', 'administrator@example.com')->exists())->toBeFalse();
})->with(['production', 'testing']);

it('seeds the example admin in local development', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::query()->where('email', 'administrator@example.com')->firstOrFail()->hasRole('super-admin'))->toBeTrue();
});
