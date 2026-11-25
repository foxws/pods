<?php

declare(strict_types=1);

use Domain\Users\Models\User;

// An instance with its owner, so guests see the login page.
beforeEach(fn () => User::factory()->create()->assignRole('super-admin'));

it('allows icons the build left out to load from the iconify api', function () {
    $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

    expect($policy)->toMatch('/connect-src [^;]*api\.iconify\.design( |;)/');
});
