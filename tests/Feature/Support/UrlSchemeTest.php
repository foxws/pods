<?php

declare(strict_types=1);

use Foxws\Essentials\Essentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

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
