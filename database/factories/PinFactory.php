<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pin>
 */
class PinFactory extends Factory
{
    protected $model = Pin::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'environment_id' => Environment::factory(),
            'type' => PinType::Application,
            'name' => fake()->unique()->domainWord(),
        ];
    }

    /**
     * A pinned application, by name.
     */
    public function application(string $name): static
    {
        return $this->state([
            'type' => PinType::Application,
            'name' => $name,
        ]);
    }
}
