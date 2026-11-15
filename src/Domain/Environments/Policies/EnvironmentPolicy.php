<?php

declare(strict_types=1);

namespace Domain\Environments\Policies;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;

/**
 * Pods has a single owner, the super-admin. Every ability goes through here, so
 * invited users with a role per environment can be added later.
 */
class EnvironmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Environment $environment): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Environment $environment): bool
    {
        return $user->isSuperAdmin();
    }

    public function operate(User $user, Environment $environment): bool
    {
        return $user->isSuperAdmin();
    }
}
