<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Domain\Users\Models\User;
use Spatie\Permission\Models\Role;

it('seeds the roles but never a user', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(Role::query()->pluck('name')->all())->toContain('admin', 'super-admin')
        ->and(User::query()->exists())->toBeFalse();
})->with(['production', 'local']);
