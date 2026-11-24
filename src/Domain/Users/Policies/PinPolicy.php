<?php

declare(strict_types=1);

namespace Domain\Users\Policies;

use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Pins are each user's own. Another user's pin is a 404, so its existence doesn't show.
 */
class PinPolicy
{
    public function delete(User $user, Pin $pin): Response
    {
        return $pin->user()->is($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
