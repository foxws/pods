<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Foundation\Settings\ProxySettings;
use Modules\Web\Settings\Controllers\ProxyDefaultsController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
});

it('shows the proxy defaults to a super-admin', function () {
    $this->actingAs($this->owner)
        ->get(action([ProxyDefaultsController::class, 'show']))
        ->assertOk()
        ->assertExactJson([
            'mode' => 'letsencrypt',
            'email' => null,
            'domain' => null,
            'dns_provider' => 'cloudflare',
            'resolvers' => null,
            'http_port' => 80,
            'https_port' => 443,
            'image' => null,
        ]);
});

it('saves the proxy defaults, without a DNS token', function () {
    $this->actingAs($this->owner)
        ->put(action([ProxyDefaultsController::class, 'update']), [
            'mode' => 'wildcard',
            'email' => 'you@example.com',
            'domain' => 'Home.Example.com',
            'dns_provider' => 'cloudflare',
            'dns_token' => 'secret-token',
            'resolvers' => '1.1.1.1',
            'http_port' => 28080,
            'https_port' => 28443,
            'image' => null,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(app(ProxySettings::class)->toArray())->toBe([
        'mode' => 'wildcard',
        'email' => 'you@example.com',
        'domain' => 'home.example.com',
        'dns_provider' => 'cloudflare',
        'resolvers' => '1.1.1.1',
        'http_port' => 28080,
        'https_port' => 28443,
        'image' => null,
    ])->and(json_encode(DB::table('settings')->where('group', 'proxy')->pluck('payload')))->not->toContain('secret-token');
});

it('refuses proxy defaults lpod would refuse', function (array $values, string $field) {
    $this->actingAs($this->owner)
        ->put(action([ProxyDefaultsController::class, 'update']), [
            'mode' => 'letsencrypt',
            'dns_provider' => 'cloudflare',
            'http_port' => 80,
            'https_port' => 443,
            ...$values,
        ])
        ->assertSessionHasErrors($field);

    expect(app(ProxySettings::class)->mode)->toBe('letsencrypt');
})->with([
    'an unknown mode' => [['mode' => 'acme'], 'mode'],
    'a domain with a scheme' => [['domain' => 'https://example.com'], 'domain'],
    'a domain of one label' => [['domain' => 'example'], 'domain'],
    'an unknown DNS provider' => [['dns_provider' => 'route53'], 'dns_provider'],
    'the same port twice' => [['https_port' => 80], 'https_port'],
    'an image with a space' => [['image' => 'caddy 2'], 'image'],
]);

it('only lets a super-admin read or change the proxy defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(action([ProxyDefaultsController::class, 'show']))->assertForbidden();
    $this->actingAs($user)->put(action([ProxyDefaultsController::class, 'update']), ['mode' => 'http'])->assertForbidden();
});
