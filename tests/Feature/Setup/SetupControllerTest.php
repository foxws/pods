<?php

declare(strict_types=1);

use Domain\Users\Actions\OwnerSetup;
use Domain\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Web\Setup\Controllers\SetupController;

function ownerInput(array $overrides = []): array
{
    return [
        'code' => app(OwnerSetup::class)->code(),
        'name' => 'Jane Owner',
        'email' => 'jane@example.com',
        'password' => 'Sup3r-Secret-Passw0rd!',
        'password_confirmation' => 'Sup3r-Secret-Passw0rd!',
        ...$overrides,
    ];
}

it('sends guests to the setup page while there is no owner', function () {
    $this->get(route('login'))->assertRedirect(route('setup.show'));
    $this->get(route('home'))->assertRedirect(route('setup.show'));
});

it('shows the setup page while there is no owner', function () {
    $this->get(route('setup.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/AuthSetup')
            ->where('action', '/setup'));
});

it('creates a verified owner, logs them in and forgets the code', function () {
    $this->post(action([SetupController::class, 'store']), ownerInput(['code' => strtolower(' '.app(OwnerSetup::class)->code())]))
        ->assertRedirect(route('home'));

    $owner = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($owner->hasRole('super-admin'))->toBeTrue()
        ->and($owner->hasVerifiedEmail())->toBeTrue()
        ->and(Storage::disk('local')->exists('setup-code'))->toBeFalse();

    $this->assertAuthenticatedAs($owner);
});

it('refuses a wrong setup code', function () {
    app(OwnerSetup::class)->code();

    $this->post(action([SetupController::class, 'store']), ownerInput(['code' => 'AAAA-BBBB-CCCC-DDDD']))
        ->assertSessionHasErrors('code');

    expect(User::query()->exists())->toBeFalse();
    $this->assertGuest();
});

it('is gone once the owner exists', function () {
    $code = app(OwnerSetup::class)->code();
    User::factory()->create()->assignRole('super-admin');

    $this->get(route('setup.show'))->assertNotFound();
    $this->post(action([SetupController::class, 'store']), ownerInput(['code' => $code]))->assertNotFound();
    $this->get(route('login'))->assertOk();
});
