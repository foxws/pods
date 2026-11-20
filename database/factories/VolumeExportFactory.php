<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolumeExport>
 */
class VolumeExportFactory extends Factory
{
    protected $model = VolumeExport::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'user_id' => User::factory(),
            'volume' => 'immich-library',
            'stop_applications' => false,
            'status' => VolumeExportStatus::Pending,
        ];
    }

    /**
     * Finished, with an archive on the backups disk.
     */
    public function completed(string $contents = 'archive'): static
    {
        return $this
            ->state(fn (): array => [
                'status' => VolumeExportStatus::Completed,
                'started_at' => now(),
                'finished_at' => now(),
            ])
            ->afterCreating(function (VolumeExport $export) use ($contents): void {
                $path = $export->archivePath();

                VolumeExport::disk()->put($path, $contents);

                $export->update(['path' => $path, 'size' => strlen($contents)]);
            });
    }

    public function failed(string $error = 'lpod volume export immich-library --json failed: No volume named immich-library.'): static
    {
        return $this->state(fn (): array => [
            'status' => VolumeExportStatus::Failed,
            'error' => $error,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
