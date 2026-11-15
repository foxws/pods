<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Secrets\Controllers\SecretController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists existing secrets and the ones quadlets miss', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([SecretController::class, 'index'], [$this->environment, 'secret' => 'ha-mqtt-password', 'application' => 'home-assistant']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Secrets/SecretIndex')
            ->where('prefill', ['name' => 'ha-mqtt-password', 'application' => 'home-assistant'])
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('secrets.data', 6)
                ->where('secrets.data.4.used_by', [])
                ->where('secrets.data.5.name', 'ha-mqtt-password')
                ->where('secrets.data.5.missing', true)
                ->has('applications.data', 6)));
});

it('ignores a prefill that is not a plain name', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([SecretController::class, 'index'], [$this->environment, 'secret' => '$(reboot)']))
        ->assertInertia(fn ($page) => $page->where('prefill.name', null));
});

it('creates a secret, adds it to an application and restarts it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([SecretController::class, 'store'], $this->environment), [
            'name' => 'ha-mqtt-password',
            'driver' => 'file',
            'value' => 'correct horse battery staple',
            'attachments' => [
                ['application' => 'home-assistant', 'type' => 'env', 'target' => 'MQTT_PASSWORD'],
            ],
            'restart' => true,
        ])
        ->assertRedirect(action([SecretController::class, 'index'], $this->environment))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['secret', 'create', 'ha-mqtt-password', '--driver', 'file', '--stdin', '--json']
        && $input === 'correct horse battery staple');
    $lpod->assertRan(['home-assistant', 'secret', 'add', 'ha-mqtt-password', '--type', 'env', '--target', 'MQTT_PASSWORD', '--json']);
    $lpod->assertRan(['home-assistant', 'restart', '--no-block', '--json']);
});

it('never puts the secret value in the arguments', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([SecretController::class, 'store'], $this->environment), [
        'name' => 'backup-key',
        'driver' => 'file',
        'value' => 'super-secret-value',
    ]);

    foreach ($lpod->recorded() as $call) {
        expect(implode(' ', $call['arguments']))->not->toContain('super-secret-value');
    }

    $lpod->assertRan(fn (array $arguments) => $arguments[0] === 'secret');
});

it('does not restart applications unless asked', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([SecretController::class, 'store'], $this->environment), [
        'name' => 'ha-mqtt-password',
        'driver' => 'file',
        'value' => 'value',
        'attachments' => [['application' => 'home-assistant', 'type' => 'env', 'target' => 'MQTT_PASSWORD']],
    ]);

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments) => in_array('restart', $arguments, true)))->toBeFalse();
});

it('rejects names and targets that are not plain', function (array $input, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([SecretController::class, 'store'], $this->environment), [
            'name' => 'valid-name',
            'driver' => 'file',
            'value' => 'value',
            ...$input,
        ])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'option-like name' => [['name' => '--help'], 'name'],
    'shell in name' => [['name' => 'a;reboot'], 'name'],
    'unknown driver' => [['driver' => 'vault'], 'driver'],
    'shell in target' => [['attachments' => [['application' => 'app', 'type' => 'env', 'target' => '$(reboot)']]], 'attachments.0.target'],
]);

it('does not keep the value as old input after a failed validation', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([SecretController::class, 'store'], $this->environment), [
            'name' => '--bad',
            'driver' => 'file',
            'value' => 'super-secret-value',
        ])
        ->assertSessionHasErrors('name');

    expect(session()->getOldInput('value'))->toBeNull();
});

it('flashes the error when lpod cannot create the secret', function () {
    fakeLpod()->respond('secret create *', new HostResult(1, '', 'Error: secret name in use'));

    $this->actingAs($this->owner)
        ->post(action([SecretController::class, 'store'], $this->environment), [
            'name' => 'immich-db-password',
            'driver' => 'file',
            'value' => 'value',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([SecretController::class, 'store'], $this->environment), [
            'name' => 'valid-name',
            'driver' => 'file',
            'value' => 'value',
        ])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
