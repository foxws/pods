<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Foundation\Settings\ProxySettings;
use Integrations\Ssh\HostResult;
use Integrations\Ssh\Testing\FakeHostRunner;
use Modules\Web\Environments\Controllers\EnvironmentProxyController;
use Modules\Web\Environments\Controllers\EnvironmentSettingsController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create(['name' => 'Home Server']);
});

/**
 * @return array<string, mixed>|null
 */
function proxySettingsSent(FakeHostRunner $lpod): ?array
{
    $call = collect($lpod->recorded())->first(fn (array $call): bool => $call['arguments'] === ['proxy', 'settings', 'set', '--stdin', '--json']);

    return $call !== null ? json_decode((string) $call['input'], true) : null;
}

it('shows the settings of the proxy', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('proxy', fn ($reload) => $reload
            ->where('proxy.error', null)
            ->where('proxy.data', [
                'installed' => true,
                'running' => true,
                'managed' => true,
                'mode' => 'wildcard',
                'email' => 'you@example.com',
                'domain' => 'home.example.com',
                'dns_provider' => 'cloudflare',
                'resolvers' => '1.1.1.1',
                'image' => null,
                'http_port' => 80,
                'https_port' => 443,
                'dns_token' => true,
                'rootless' => true,
                'unprivileged_port_start' => 80,
            ])));

    $lpod->assertRan(['proxy', 'settings', '--json']);
});

it('gives the settings page the proxy defaults, for a host without a proxy', function () {
    fakeLpod();
    app(ProxySettings::class)->fill(['mode' => 'local', 'http_port' => 28080, 'https_port' => 28443])->save();

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->where('proxyDefaults.mode', 'local')
            ->where('proxyDefaults.http_port', 28080)
            ->where('proxyDefaults.https_port', 28443)
            ->missing('proxyDefaults.dns_token'));
});

it('shows the error when lpod cannot read the settings of the proxy', function () {
    fakeLpod()->respond('proxy settings --json', new HostResult(1, '', 'Failed to connect to bus'));

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('proxy', fn ($reload) => $reload
            ->where('proxy.data', null)
            ->where('proxy.error', fn (string $error) => str_contains($error, 'Failed to connect to bus'))));
});

it('saves the settings of the proxy, with the DNS token only on stdin', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([EnvironmentProxyController::class, 'update'], $this->environment), [
            'mode' => 'wildcard',
            'email' => 'you@example.com',
            'domain' => 'Home.Example.com',
            'dns_provider' => 'cloudflare',
            'dns_token' => 'cf-token',
            'resolvers' => '1.1.1.1',
            'http_port' => 80,
            'https_port' => 443,
            'image' => null,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('description', 'lpod rewrote the Caddyfile and the routes, and restarted the proxy. Your own Caddyfile is kept as /home/blue/.config/pods-proxy/Caddyfile.20261117152546.backup.');

    expect(proxySettingsSent($lpod))->toBe([
        'mode' => 'wildcard',
        'email' => 'you@example.com',
        'domain' => 'home.example.com',
        'dns_provider' => 'cloudflare',
        'resolvers' => '1.1.1.1',
        'http_port' => 80,
        'https_port' => 443,
        'image' => null,
        'dns_token' => 'cf-token',
    ]);

    foreach ($lpod->recorded() as $call) {
        expect($call['arguments'])->not->toContain('cf-token');
    }
});

it('leaves out what the mode does not use, and keeps the DNS token on the host when none is given', function () {
    $lpod = fakeLpod()->respond('proxy settings set --stdin --json', json_encode(['contract' => 1, 'ok' => true, 'backup' => null]));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentProxyController::class, 'update'], $this->environment), [
            'mode' => 'http',
            'domain' => 'home.example.com',
            'dns_provider' => 'cloudflare',
            'dns_token' => 'cf-token',
            'http_port' => 28080,
            'https_port' => 28080,
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('description', 'lpod rewrote the Caddyfile and the routes, and restarted the proxy.');

    expect(proxySettingsSent($lpod))->toBe([
        'mode' => 'http',
        'email' => null,
        'domain' => null,
        'dns_provider' => null,
        'resolvers' => null,
        'http_port' => 28080,
        'https_port' => null,
        'image' => null,
    ]);
});

it('validates the settings of the proxy', function (array $settings, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([EnvironmentProxyController::class, 'update'], $this->environment), [
            'mode' => 'letsencrypt',
            'http_port' => 80,
            'https_port' => 443,
            ...$settings,
        ])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'an unknown mode' => [['mode' => 'acme'], 'mode'],
    'an invalid email' => [['email' => 'you'], 'email'],
    'a wildcard without a domain' => [['mode' => 'wildcard', 'dns_provider' => 'cloudflare'], 'domain'],
    'a wildcard domain of one label' => [['mode' => 'wildcard', 'domain' => 'example', 'dns_provider' => 'cloudflare'], 'domain'],
    'an unknown DNS provider' => [['mode' => 'wildcard', 'domain' => 'home.example.com', 'dns_provider' => 'route53'], 'dns_provider'],
    'a token with whitespace' => [['mode' => 'wildcard', 'domain' => 'home.example.com', 'dns_provider' => 'cloudflare', 'dns_token' => "a\nb"], 'dns_token'],
    'resolvers with other characters' => [['mode' => 'wildcard', 'domain' => 'home.example.com', 'dns_provider' => 'cloudflare', 'resolvers' => '1.1.1.1; reboot'], 'resolvers'],
    'a port out of range' => [['http_port' => 70000], 'http_port'],
    'the same port twice' => [['https_port' => 80], 'https_port'],
    'an image with spaces' => [['image' => 'caddy 2'], 'image'],
]);

it('shows the error when lpod refuses the settings of the proxy', function () {
    fakeLpod()->respond('proxy settings set --stdin --json', new HostResult(1, '', 'The DNS challenge needs an API token of cloudflare: pass dns_token.'));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentProxyController::class, 'update'], $this->environment), [
            'mode' => 'wildcard',
            'domain' => 'home.example.com',
            'dns_provider' => 'cloudflare',
            'http_port' => 80,
            'https_port' => 443,
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'Proxy settings not saved')
        ->assertInertiaFlash('description', 'lpod proxy settings set --stdin --json failed: The DNS challenge needs an API token of cloudflare: pass dns_token.');
});

it('installs the proxy from Pods\' repository, saves its settings, then starts it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([EnvironmentProxyController::class, 'store'], $this->environment), [
            'mode' => 'local',
            'http_port' => 28080,
            'https_port' => 28443,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'Proxy installed');

    $commands = collect($lpod->recorded())->pluck('arguments');
    $fetch = $commands->search(['quadlet', 'fetch', 'https://github.com/foxws/pods', '--ref', config('catalog.proxy.reference'), '--path', 'containers/proxy', '--json']);
    $install = $commands->search(['quadlet', 'install', 'pods-proxy', '--stdin', '--json']);
    $settings = $commands->search(['proxy', 'settings', 'set', '--stdin', '--json']);
    $start = $commands->search(['pods-proxy', 'up', '--no-block', '--json']);

    expect($fetch)->toBeInt()
        ->and($install)->toBeGreaterThan($fetch)
        ->and($settings)->toBeGreaterThan($install)
        ->and($start)->toBeGreaterThan($settings)
        ->and(proxySettingsSent($lpod))->toMatchArray(['mode' => 'local', 'http_port' => 28080, 'https_port' => 28443])
        ->and($this->environment->installations()->sole())
        ->application->toBe('pods-proxy')
        ->repository->toBe('https://github.com/foxws/pods')
        ->reference->toBe(config('catalog.proxy.reference'))
        ->path->toBe('containers/proxy')
        ->commit->toBe('a41f9c2');
});

it('starts the installed proxy even when its settings cannot be saved', function () {
    $lpod = fakeLpod()->respond('proxy settings set --stdin --json', new HostResult(1, '', 'Permission denied'));

    $this->actingAs($this->owner)
        ->post(action([EnvironmentProxyController::class, 'store'], $this->environment), [
            'mode' => 'letsencrypt',
            'http_port' => 80,
            'https_port' => 443,
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'Proxy installed, but its settings weren\'t saved');

    $lpod->assertRan(['pods-proxy', 'up', '--no-block', '--json']);
});

it('neither saves settings nor starts anything when the proxy cannot be installed', function () {
    $lpod = fakeLpod()->respond('quadlet install *', new HostResult(1, '', 'pods-proxy.container already exists'));

    $this->actingAs($this->owner)
        ->post(action([EnvironmentProxyController::class, 'store'], $this->environment), [
            'mode' => 'letsencrypt',
            'http_port' => 80,
            'https_port' => 443,
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'Proxy not installed');

    expect(proxySettingsSent($lpod))->toBeNull()
        ->and(collect($lpod->recorded())->pluck('arguments'))->not->toContain(['pods-proxy', 'up', '--no-block', '--json']);
});

it('downloads the root certificate of the proxy', function () {
    fakeLpod();

    $response = $this->actingAs($this->owner)
        ->get(action([EnvironmentProxyController::class, 'certificate'], $this->environment))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/x-x509-ca-cert')
        ->assertDownload('pods-proxy-home-server.crt');

    expect($response->getContent())->toStartWith("-----BEGIN CERTIFICATE-----\n")->toEndWith("-----END CERTIFICATE-----\n");
});

it('says so when the proxy has no local certificate yet', function () {
    fakeLpod()->respond('proxy certificate --json', json_encode(['contract' => 1, 'certificate' => null, 'fingerprint' => null, 'expires' => null]));

    $this->actingAs($this->owner)
        ->get(action([EnvironmentProxyController::class, 'certificate'], $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('title', 'No certificate yet');
});

it('forbids users who are not the owner to change the proxy', function () {
    $lpod = fakeLpod();
    $user = User::factory()->create();
    $settings = ['mode' => 'letsencrypt', 'http_port' => 80, 'https_port' => 443];

    $this->actingAs($user)->post(action([EnvironmentProxyController::class, 'store'], $this->environment), $settings)->assertForbidden();
    $this->actingAs($user)->put(action([EnvironmentProxyController::class, 'update'], $this->environment), $settings)->assertForbidden();
    $this->actingAs($user)->get(action([EnvironmentProxyController::class, 'certificate'], $this->environment))->assertForbidden();

    $lpod->assertNothingRan();
});
