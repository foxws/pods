<?php

declare(strict_types=1);

namespace Domain\Users\Actions;

use Domain\Users\Models\User;
use Foundation\Settings\GeneralSettings;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        protected CreateUser $createUser,
    ) {}

    /**
     * Fortify's registration, only while it's open. People who register verify their email
     * address themselves.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort_unless(app(GeneralSettings::class)->allow_registration, 404);

        return ($this->createUser)($input, verified: false);
    }
}
