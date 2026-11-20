<?php

declare(strict_types=1);

use Domain\Users\Models\User;

// An instance with its owner, so guests see the login page.
beforeEach(fn () => User::factory()->create()->assignRole('super-admin'));

it('links the manifest and registers the service worker with the csp nonce', function () {
    $response = $this->get(route('login'))->assertOk();

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);

    $response
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('/images/icons/apple-touch-icon.png', escape: false)
        ->assertSee('navigator.serviceWorker', escape: false)
        ->assertSee('nonce="'.$nonce[1].'"', escape: false);
});

it('ships every icon and screenshot the manifest names in the public folder', function () {
    $paths = collect(config('pwa.icons'))->pluck('path')
        ->merge(collect(config('pwa.manifest.screenshots'))->pluck('src'))
        ->push(config('pwa.apple_touch_icon'));

    expect($paths)->each(fn ($path) => $path->toStartWith('/images/'));

    $paths->each(fn (string $path) => expect(public_path($path))->toBeFile());
});
