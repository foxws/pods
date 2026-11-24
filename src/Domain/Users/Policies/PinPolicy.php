<?php

declare(strict_types=1);

namespace Domain\Users\Policies;

use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Pins are each user's own, so there's no admin bypass. Another user's pin is a 404, so its existence doesn't show.
 * Pinning something on a host also needs `view` on its environment, which the request checks.
 */
class PinPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pin $pin): Response
    {
        return $this->owns($user, $pin);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Pin $pin): Response
    {
        return $this->owns($user, $pin);
    }

    public function delete(User $user, Pin $pin): Response
    {
        return $this->owns($user, $pin);
    }

    public function restore(User $user, Pin $pin): bool
    {
        return false;
    }

    public function forceDelete(User $user, Pin $pin): bool
    {
        return false;
    }

    protected function owns(User $user, Pin $pin): Response
    {
        return $pin->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
