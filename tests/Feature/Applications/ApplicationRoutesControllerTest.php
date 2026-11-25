<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Foundation\Settings\ProxySettings;
use Integrations\Ssh\HostResult;
use Integrations\Ssh\Testing\FakeHostRunner;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationRoutesController;
use Modules\Web\Quadlets\Controllers\QuadletController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

/**
 * The routes lpod was given on stdin by "APP routes set".
 *
 * @return list<array<string, mixed>>|null
 */
function routesSentTo(FakeHostRunner $lpod, string $application): ?array
{
    $call = collect($lpod->recorded())->first(fn (array $call): bool => $call['arguments'] === [$application, 'routes', 'set', '--stdin', '--json']);

    return $call !== null ? json_decode((string) $call['input'], true)['routes'] : null;
}

it('shows the routes of an application, then the ones its pods.yml names with a suggested hostname', function () {
    fakeLpod();
    Installation::factory()->for($this->environment)->create([
        'application' => 'hello',
        'manifest' => <<<'YAML'
            routes:
              app:
                upstream: systemd-hello:8000
                description: The web app
              websockets:
                upstream: systemd-hello-reverb:6001
              s3:
                upstream: systemd-hello-rustfs:9000
                description: Object storage
                via: main
              mail:
                upstream: systemd-hello-mailpit:8025
                description: Mailpit
            YAML,
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('routes', fn ($reload) => $reload
            ->where('routes.data.proxy_installed', true)
            ->where('routes.data.proxy_running', true)
            ->where('routes.data.layout', 'sites')
            ->where('routes.data.routes', fn ($routes): bool => collect($routes)->map(fn ($route): array => collect($route)->only(['role', 'host', 'suggested_host', 'target', 'main', 'via', 'from_manifest', 'description'])->all())->all() === [
                ['role' => 'app', 'description' => 'The web app', 'main' => true, 'via' => null, 'host' => 'hello.home.arpa', 'suggested_host' => 'hello.home.arpa', 'target' => 'host.containers.internal:8000', 'from_manifest' => true],
                ['role' => 'websockets', 'description' => null, 'main' => false, 'via' => null, 'host' => 'ws.hello.home.arpa', 'suggested_host' => 'websockets.hello.home.arpa', 'target' => 'systemd-hello-reverb:6001', 'from_manifest' => true],
                ['role' => 's3', 'description' => 'Object storage', 'main' => false, 'via' => 'main', 'host' => 's3.hello.home.arpa', 'suggested_host' => 's3.hello.home.arpa', 'target' => 'host.containers.internal:8000', 'from_manifest' => true],
                ['role' => 'mail', 'description' => 'Mailpit', 'main' => false, 'via' => null, 'host' => null, 'suggested_host' => 'mail.hello.home.arpa', 'target' => null, 'from_manifest' => true],
            ])));
});

/**
 * The reachable upstreams the application page offers for an application.
 *
 * @return list<array<string, mixed>>
 */
function reachableOn(mixed $test, string $application): array
{
    $reachable = null;

    $test->actingAs($test->owner)
        ->get(action([ApplicationController::class, 'show'], [$test->environment, $application]))
        ->assertInertia(function ($page) use (&$reachable) {
            $page->loadDeferredProps('routes', function ($reload) use (&$reachable) {
                $reachable = $reload->toArray()['props']['routes']['data']['reachable'];
            });
        });

    return $reachable;
}

/**
 * One container of an application, as "lpod containers --json" lists it.
 *
 * @param  list<string>  $ports
 * @return array<string, mixed>
 */
function hostContainer(string $name, string $application, array $ports = [], ?string $pod = null): array
{
    return ['id' => md5($name), 'name' => $name, 'state' => 'running', 'application' => $application, 'pod' => $pod, 'ports' => $ports];
}

/**
 * One network, as "lpod networks --json" lists it.
 *
 * @param  list<string>  $applications
 * @return array<string, mixed>
 */
function hostNetwork(string $name, array $applications, string $driver = 'bridge', bool $dns = true): array
{
    return ['name' => $name, 'driver' => $driver, 'dns' => $dns, 'applications' => $applications, 'default' => false];
}

it('offers a pod by name on its network, and a port published on every address through the host', function () {
    fakeLpod();

    expect(reachableOn($this, 'immich'))->toBe([
        ['container' => 'immich', 'reach' => 'network', 'name' => 'immich', 'port' => 2283, 'published' => '0.0.0.0:2283->2283/tcp'],
        ['container' => 'immich', 'reach' => 'host', 'name' => 'host.containers.internal', 'port' => 2283, 'published' => '0.0.0.0:2283->2283/tcp'],
    ]);
});

it('tells where an application without a network can be reached', function (array $container, array $reachable) {
    fakeLpod()
        ->respond('containers --json', json_encode(['contract' => 1, 'containers' => [$container]]))
        ->respond('networks --json', json_encode(['contract' => 1, 'networks' => [hostNetwork('iot', ['hello'], driver: 'macvlan', dns: false)]]));

    expect(reachableOn($this, 'hello'))->toBe($reachable);
})->with([
    'published on the LAN address' => [
        hostContainer('systemd-hello', 'hello', ['127.0.0.1:3000->3000/tcp', '192.168.1.10:3000->3000/tcp', '0.0.0.0:53->53/udp']),
        [
            ['container' => 'systemd-hello', 'reach' => 'loopback', 'name' => 'host.containers.internal', 'port' => 3000, 'published' => '127.0.0.1:3000->3000/tcp'],
            ['container' => 'systemd-hello', 'reach' => 'host', 'name' => 'host.containers.internal', 'port' => 3000, 'published' => '192.168.1.10:3000->3000/tcp'],
        ],
    ],
    'published on loopback only' => [
        hostContainer('systemd-hello', 'hello', ['127.0.0.1:3000->3000/tcp']),
        [['container' => 'systemd-hello', 'reach' => 'loopback', 'name' => 'host.containers.internal', 'port' => 3000, 'published' => '127.0.0.1:3000->3000/tcp']],
    ],
    'on the host network, publishing nothing' => [
        hostContainer('systemd-hello', 'hello'),
        [['container' => 'systemd-hello', 'reach' => 'unknown', 'name' => 'systemd-hello', 'port' => null, 'published' => null]],
    ],
]);

it('offers a container on a network by name, on the port inside it, without its loopback publish', function () {
    fakeLpod()
        ->respond('containers --json', json_encode(['contract' => 1, 'containers' => [
            hostContainer('systemd-hello', 'hello', ['127.0.0.1:8222->80/tcp']),
            hostContainer('systemd-hello-worker', 'hello'),
            hostContainer('systemd-other', 'other', ['0.0.0.0:9000->9000/tcp']),
        ]]))
        ->respond('networks --json', json_encode(['contract' => 1, 'networks' => [hostNetwork('systemd-hello', ['hello'])]]));

    expect(reachableOn($this, 'hello'))->toBe([
        ['container' => 'systemd-hello', 'reach' => 'network', 'name' => 'systemd-hello', 'port' => 80, 'published' => '127.0.0.1:8222->80/tcp'],
        ['container' => 'systemd-hello-worker', 'reach' => 'network', 'name' => 'systemd-hello-worker', 'port' => null, 'published' => null],
    ]);
});

it('gives the routes modal the kinds of upstream, with an example of the ones a route can use', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->where('upstreamReaches', fn ($reaches): bool => collect($reaches)
            ->map(fn (array $reach): array => [$reach['value'], $reach['example'], $reach['usable']])
            ->all() === [
                ['network', 'systemd-hello:8000', true],
                ['host', 'host.containers.internal:3000', true],
                ['machine', '192.168.1.20:8080', true],
                ['loopback', null, false],
                ['unknown', null, false],
            ]));
});

it('still shows the routes when the host cannot list its containers', function () {
    fakeLpod()->respond('containers --json', new HostResult(1, '', 'podman ps failed'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('routes', fn ($reload) => $reload
            ->where('routes.error', null)
            ->where('routes.data.proxy_installed', true)
            ->where('routes.data.reachable', [])));
});

it('says when the proxy is not installed', function () {
    fakeLpod()->respond('proxy routes --json', json_encode(['contract' => 1, 'proxy' => ['installed' => false, 'running' => false], 'routes' => []]));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'vaultwarden']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('routes', fn ($reload) => $reload
            ->where('routes.data.proxy_installed', false)
            ->where('routes.data.routes', [])));
});

it('saves the routes with lowercase hostnames and one main route', function (array $routes, array $sent) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationRoutesController::class, 'update'], [$this->environment, 'hello']), ['routes' => $routes])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(routesSentTo($lpod, 'hello'))->toBe($sent);
})->with([
    'the first is the main one' => [
        [['role' => 'app', 'host' => 'Hello.Home.Arpa', 'upstream' => 'systemd-hello:8000'], ['role' => 'websockets', 'host' => 'ws.hello.home.arpa', 'upstream' => 'systemd-hello-reverb:6001']],
        [['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000', 'main' => true, 'via' => null], ['role' => 'websockets', 'host' => 'ws.hello.home.arpa', 'upstream' => 'systemd-hello-reverb:6001', 'main' => false, 'via' => null]],
    ],
    'the first that says so' => [
        [['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000', 'main' => false], ['role' => 'websockets', 'host' => 'ws.hello.home.arpa', 'upstream' => 'systemd-hello-reverb:6001', 'main' => true], ['role' => 's3', 'host' => 's3.hello.home.arpa', 'upstream' => 'systemd-hello-rustfs:9000', 'main' => true]],
        [['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000', 'main' => false, 'via' => null], ['role' => 'websockets', 'host' => 'ws.hello.home.arpa', 'upstream' => 'systemd-hello-reverb:6001', 'main' => true, 'via' => null], ['role' => 's3', 'host' => 's3.hello.home.arpa', 'upstream' => 'systemd-hello-rustfs:9000', 'main' => false, 'via' => null]],
    ],
    'to the host or another machine' => [
        [['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'host.containers.internal:3000'], ['role' => 'admin', 'host' => 'admin.hello.home.arpa', 'upstream' => '192.168.1.20:8080']],
        [['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'host.containers.internal:3000', 'main' => true, 'via' => null], ['role' => 'admin', 'host' => 'admin.hello.home.arpa', 'upstream' => '192.168.1.20:8080', 'main' => false, 'via' => null]],
    ],
    'none, which removes them' => [[], []],
]);

it('suggests hostnames one label under the wildcard site of the proxy', function () {
    fakeLpod()->respond('proxy routes --json', json_encode(['contract' => 1, 'proxy' => ['installed' => true, 'running' => true, 'layout' => 'handlers', 'domain' => 'home.example.com'], 'routes' => []]));
    Installation::factory()->for($this->environment)->create([
        'application' => 'hello',
        'manifest' => "routes:\n  app:\n    upstream: systemd-hello:8000\n  ws:\n    upstream: systemd-hello-reverb:6001\n    via: main\n",
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('routes', fn ($reload) => $reload
            ->where('routes.data.layout', 'handlers')
            ->where('routes.data.domain', 'home.example.com')
            ->where('routes.data.routes.0.suggested_host', 'hello.home.example.com')
            ->where('routes.data.routes.1.suggested_host', 'hello-ws.home.example.com')
            ->where('routes.data.routes.1.via', 'main')));
});

it('suggests hostnames under the domain of the proxy defaults, but not behind a wildcard site', function (string $layout, ?string $wildcard, string $main, string $other) {
    fakeLpod()->respond('proxy routes --json', json_encode(['contract' => 1, 'proxy' => ['installed' => true, 'running' => true, 'layout' => $layout, 'domain' => $wildcard], 'routes' => []]));
    app(ProxySettings::class)->fill(['domain' => 'example.com'])->save();
    Installation::factory()->for($this->environment)->create([
        'application' => 'hello',
        'manifest' => "routes:\n  app:\n    upstream: systemd-hello:8000\n  ws:\n    upstream: systemd-hello-reverb:6001\n",
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('routes', fn ($reload) => $reload
            ->where('routes.data.default_domain', 'example.com')
            ->where('routes.data.routes.0.suggested_host', $main)
            ->where('routes.data.routes.1.suggested_host', $other)));
})->with([
    'a site per hostname' => ['sites', null, 'hello.example.com', 'ws.hello.example.com'],
    'a wildcard site' => ['handlers', 'home.example.com', 'hello.home.example.com', 'hello-ws.home.example.com'],
]);

it('gives the install page the domain of the proxy defaults', function () {
    fakeLpod();
    app(ProxySettings::class)->fill(['domain' => 'example.com'])->save();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page->where('defaultDomain', 'example.com'));
});

it('sends a route through the main one, but never the main route itself', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationRoutesController::class, 'update'], [$this->environment, 'hello']), ['routes' => [
            ['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000', 'via' => 'main'],
            ['role' => 'ws', 'host' => 'ws.hello.home.arpa', 'upstream' => 'systemd-hello-reverb:6001', 'via' => 'main'],
        ]])
        ->assertInertiaFlash('type', 'success');

    expect(array_column(routesSentTo($lpod, 'hello'), 'via', 'role'))->toBe(['app' => null, 'ws' => 'main']);
});

it('refuses routes lpod would refuse', function (array $route, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationRoutesController::class, 'update'], [$this->environment, 'hello']), ['routes' => [
            ['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000'],
            $route,
        ]])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'a host that is no hostname' => [['role' => 'web', 'host' => 'https://hello.home.arpa', 'upstream' => 'systemd-hello:8000'], 'routes.1.host'],
    'the same host in other case' => [['role' => 'web', 'host' => 'HELLO.home.arpa', 'upstream' => 'systemd-hello:8000'], 'routes.1.host'],
    'the same role twice' => [['role' => 'app', 'host' => 'www.hello.home.arpa', 'upstream' => 'systemd-hello:8000'], 'routes.1.role'],
    'an upstream without a port' => [['role' => 'web', 'host' => 'www.hello.home.arpa', 'upstream' => 'systemd-hello'], 'routes.1.upstream'],
    'via anything but main' => [['role' => 'web', 'host' => 'www.hello.home.arpa', 'upstream' => 'systemd-hello:8000', 'via' => 'ws'], 'routes.1.via'],
]);

it('shows the error when lpod cannot save the routes', function () {
    fakeLpod()->respond('* routes set --stdin --json', new HostResult(1, '', 'Each host can only have one route.'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationRoutesController::class, 'update'], [$this->environment, 'hello']), ['routes' => [
            ['role' => 'app', 'host' => 'hello.home.arpa', 'upstream' => 'systemd-hello:8000'],
        ]])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod hello routes set --stdin --json failed: Each host can only have one route.');
});

it('forbids users who are not the owner to change routes', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->put(action([ApplicationRoutesController::class, 'update'], [$this->environment, 'hello']), ['routes' => []])
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('routes the hostnames entered when installing, after the install', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => [['name' => 'uptime-kuma.container', 'content' => "[Container]\nImage=docker.io/louislam/uptime-kuma:2\n"]],
            'routes' => [['role' => 'app', 'host' => 'status.home.arpa', 'upstream' => 'systemd-uptime-kuma:3001']],
        ])
        ->assertRedirect(action([ApplicationController::class, 'show'], [$this->environment, 'uptime-kuma']))
        ->assertInertiaFlash('type', 'success');

    $commands = collect($lpod->recorded())->pluck('arguments');
    expect(routesSentTo($lpod, 'uptime-kuma'))->toBe([['role' => 'app', 'host' => 'status.home.arpa', 'upstream' => 'systemd-uptime-kuma:3001', 'main' => true, 'via' => null]])
        ->and($commands->search(['uptime-kuma', 'routes', 'set', '--stdin', '--json']))
        ->toBeGreaterThan($commands->search(fn (array $arguments): bool => $arguments[0] === 'quadlet' && $arguments[1] === 'install'));
});

it('still opens the installed application when its routes cannot be saved', function () {
    fakeLpod()->respond('* routes set --stdin --json', new HostResult(1, '', 'caddy reload failed'));

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => [['name' => 'uptime-kuma.container', 'content' => "[Container]\nImage=docker.io/louislam/uptime-kuma:2\n"]],
            'routes' => [['role' => 'app', 'host' => 'status.home.arpa', 'upstream' => 'systemd-uptime-kuma:3001']],
        ])
        ->assertRedirect(action([ApplicationController::class, 'show'], [$this->environment, 'uptime-kuma']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'uptime-kuma installed, but its routes weren\'t saved');
});

it('reads the routes of a pods.yml, the first being the main one unless another says so', function (string $routes, array $main) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode(['contract' => 1, 'commit' => 'a41f9c2', 'manifest' => "routes:\n{$routes}", 'files' => []]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), ['repository' => 'https://codeberg.org/foxws/quadlets.git', 'reference' => 'main'])
        ->assertJsonPath('data.manifest_error', null)
        ->assertJsonPath('data.manifest.routes.*.main', $main);
})->with([
    'no main' => ["  app:\n    upstream: app:8000\n  ws:\n    upstream: app-ws:6001\n", [true, false]],
    'the second is main' => ["  app:\n    upstream: app:8000\n  ws:\n    upstream: app-ws:6001\n    main: true\n", [false, true]],
]);

it('refuses a pods.yml with routes lpod would refuse', function (string $routes, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode(['contract' => 1, 'commit' => 'a41f9c2', 'manifest' => "routes:\n{$routes}", 'files' => []]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), ['repository' => 'https://codeberg.org/foxws/quadlets.git', 'reference' => 'main'])
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message): bool => str_contains($message, $error));
})->with([
    'a role in capitals' => ["  App:\n    upstream: app:8000\n", "\"App\" isn't a valid route role"],
    'two main routes' => ["  app:\n    upstream: app:8000\n    main: true\n  ws:\n    upstream: app-ws:6001\n    main: true\n", 'only one route can be the main one'],
    'an upstream without a port' => ["  app:\n    upstream: app\n", 'routes.app.upstream'],
    'via anything but main' => ["  app:\n    upstream: app:8000\n  ws:\n    upstream: app-ws:6001\n    via: app\n", 'routes.ws.via'],
]);
