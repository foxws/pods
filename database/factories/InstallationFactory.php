<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    protected $model = Installation::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'application' => fake()->unique()->domainWord(),
            'repository' => null,
            'reference' => null,
            'path' => null,
            'commit' => null,
            'manifest' => null,
            'manifest_path' => null,
            'checksums' => [],
            'changes' => null,
            'installed_at' => now(),
            'checked_at' => now(),
        ];
    }

    /**
     * Installed from a Git repository with a pods.yml.
     */
    public function fromGit(): static
    {
        return $this->state(fn (array $attributes) => [
            'repository' => 'https://github.com/foxws/quadlets',
            'reference' => 'main',
            'path' => 'immich',
            'commit' => 'a41f9c2',
            'manifest' => "version: 1\nname: immich\nlabel: Immich\ndescription: Self-hosted photo library\nicon: i-lucide-image\n\nsources:\n  stable:\n    path: .\n",
            'manifest_path' => 'immich',
        ]);
    }

    /**
     * Installed with these files, unchanged since.
     *
     * @param  array<string, string>  $files  file names and their contents
     */
    public function withFiles(array $files): static
    {
        return $this->state(fn (array $attributes) => [
            'checksums' => Installation::checksumsOf(array_map(
                fn (string $name, string $content): array => ['name' => $name, 'content' => $content],
                array_keys($files),
                $files,
            )),
        ]);
    }
}
