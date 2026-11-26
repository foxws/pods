<?php

declare(strict_types=1);

use Foxws\Essentials\Essentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Listeners\EnforceRequestScheme;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'production');

    (new Essentials(app()))->configure();
});

afterEach(function () {
    DB::prohibitDestructiveCommands(false);
    Password::$defaultCallback = null;
});

it('keeps plain http links in production when Pods is reached on its local port', function () {
    $this->get('http://127.0.0.1:9393/')->assertRedirect('http://127.0.0.1:9393/setup');
});

it('links to https in production behind the proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.89.0.2'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get('http://pods.example.com/')
        ->assertRedirect('https://pods.example.com/setup');
});

it('keeps plain http links on an octane request', function () {
    $request = Request::create('http://127.0.0.1:9393/');
    app('url')->setRequest($request);

    (new EnforceRequestScheme)->handle(new RequestReceived(app(), app(), $request));

    expect(url('/setup'))->toBe('http://127.0.0.1:9393/setup')
        ->and($request->isSecure())->toBeFalse();
});
