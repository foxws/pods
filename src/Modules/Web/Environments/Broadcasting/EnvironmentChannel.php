<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Broadcasting;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;

class EnvironmentChannel
{
    public function join(User $user, Environment $environment): bool
    {
        return $user->can('view', $environment);
    }
}
