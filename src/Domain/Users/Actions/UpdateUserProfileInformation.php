<?php

declare(strict_types=1);

namespace Domain\Users\Actions;

use Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
        ])->validateWithBag('updateProfileInformation');

        // Password resets go to the email address, so a session alone can't change it
        if ($input['email'] !== $user->email) {
            $this->ensurePasswordIsCurrent($user, $input['current_password'] ?? null);
        }

        DB::transaction(function () use ($user, $input): void {
            if ($input['email'] !== $user->email) {
                $this->updateVerifiedUser($user, $input);

                return;
            }

            $user->updateOrFail([
                'name' => $input['name'],
            ]);
        });
    }

    protected function ensurePasswordIsCurrent(User $user, mixed $password): void
    {
        if (is_string($password) && Hash::check($password, $user->password)) {
            return;
        }

        throw ValidationException::withMessages([
            'current_password' => __('Enter your current password to change your email address.'),
        ])->errorBag('updateProfileInformation');
    }

    /**
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->saveOrFail();

        $user->sendEmailVerificationNotification();
    }
}
