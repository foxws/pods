<?php

declare(strict_types=1);

namespace Modules\Api\Environments\Broadcasting;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;

/**
 * The channels of a host's reads (`environments.ULID.TOPIC`, see `EnvironmentChanged::channelName()`):
 * whoever may see the host may listen on all of them.
 */
class EnvironmentChannel
{
    public function join(User $user, Environment $environment): bool
    {
        return $user->can('view', $environment);
    }
}
