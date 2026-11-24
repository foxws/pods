<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Applications\Models\PinnedApplication;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PinnedApplication>
 */
class PinnedApplicationFactory extends Factory
{
    protected $model = PinnedApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'environment_id' => Environment::factory(),
            'application' => fake()->unique()->domainWord(),
        ];
    }
}
