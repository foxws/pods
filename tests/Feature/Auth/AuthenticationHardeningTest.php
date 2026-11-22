<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->owner = User::factory()->create(['email' => 'owner@example.com'])->assignRole('super-admin');
});

it('believes forwarded headers only from a proxy on a private address', function (string $remote, string $ip, bool $secure) {
    Route::get('/_client', fn () => ['ip' => request()->ip(), 'secure' => request()->secure(), 'host' => request()->getHost()]);

    $this->withServerVariables(['REMOTE_ADDR' => $remote])
        ->getJson('http://pods.example.com/_client', [
            'X-Forwarded-For' => '198.51.100.7',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'attacker.example',
        ])
        ->assertExactJson(['ip' => $ip, 'secure' => $secure, 'host' => 'pods.example.com']);
})->with([
    'the proxy' => ['10.89.0.5', '198.51.100.7', true],
    'a client' => ['203.0.113.9', '203.0.113.9', false],
]);

it('limits logins per account when the address keeps changing', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'], ['X-Forwarded-For' => "198.51.100.{$attempt}"])
            ->assertRedirect();
    }

    $this->post(route('login.store'), ['email' => 'OWNER@example.com', 'password' => 'wrong-password'], ['X-Forwarded-For' => '198.51.100.200'])
        ->assertTooManyRequests();
});

it('limits logins per account and address', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])->assertRedirect();
    }

    $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])->assertTooManyRequests();
});

it('mails a reset link on the configured host, whatever the request names', function () {
    config(['app.url' => 'https://pods.example.com']);

    $this->post('http://attacker.example/forgot-password', ['email' => 'owner@example.com'], ['X-Forwarded-Host' => 'forwarded.example'])
        ->assertRedirect();

    Notification::assertSentTo(
        $this->owner,
        fn (ResetPassword $notification): bool => str_starts_with($notification->toMail($this->owner)->actionUrl, "https://pods.example.com/reset-password/{$notification->token}?"),
    );
});

it('signs a session out when the password changes elsewhere', function () {
    $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'password'])->assertRedirect();
    $this->get(route('settings.show', 'account'))->assertSuccessful();

    $this->owner->forceFill(['password' => Hash::make('An0ther-Secret-Passw0rd!')])->save();
    app('auth')->forgetGuards();

    $this->get(route('settings.show', 'account'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps the session that changes the password', function () {
    $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'password'])->assertRedirect();
    $this->get(route('settings.show', 'account'))->assertSuccessful();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'An0ther-Secret-Passw0rd!',
        'password_confirmation' => 'An0ther-Secret-Passw0rd!',
    ])->assertSessionHasNoErrors();
    app('auth')->forgetGuards();

    $this->get(route('settings.show', 'account'))->assertSuccessful();
});

it('limits guesses at the current password', function (string $method, string $route, array $data) {
    $this->actingAs($this->owner);

    foreach (range(1, 6) as $attempt) {
        $this->{$method}(route($route), $data)->assertRedirect();
    }

    $this->{$method}(route($route), $data)->assertTooManyRequests();
})->with([
    'confirming it' => ['post', 'password.confirm.store', ['password' => 'wrong-password']],
    'changing it' => ['put', 'user-password.update', ['current_password' => 'wrong-password', 'password' => 'An0ther-Secret-Passw0rd!']],
    'changing the email' => ['put', 'user-profile-information.update', ['name' => 'Owner', 'email' => 'new@example.com', 'current_password' => 'wrong-password']],
]);

it('does not limit saving the name', function () {
    $this->actingAs($this->owner);

    foreach (range(1, 7) as $attempt) {
        $this->put(route('user-profile-information.update'), ['name' => "Owner {$attempt}", 'email' => 'owner@example.com'])->assertRedirect();
    }
});
