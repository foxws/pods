<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Environments\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    protected $model = Environment::class;

    public function definition(): array
    {
        $host = fake()->unique()->domainWord().'.home.arpa';

        return [
            'name' => fake()->unique()->word(),
            'host' => $host,
            'port' => 22,
            'user' => 'blue',
            'host_key' => "{$host} ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl",
            'is_local' => false,
            'facts' => null,
            'last_seen_at' => null,
        ];
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'local',
            'host' => 'host.containers.internal',
            'host_key' => 'host.containers.internal ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl',
            'is_local' => true,
        ]);
    }
}
