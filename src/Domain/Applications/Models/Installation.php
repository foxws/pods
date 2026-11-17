<?php

declare(strict_types=1);

namespace Domain\Applications\Models;

use Database\Factories\InstallationFactory;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\Exceptions\InvalidManifestException;
use Foxws\Essentials\Casts\AsDateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An application Pods installed, with where its files came from and a checksum of each,
 * so a sync can tell when they were changed on the host outside Pods.
 *
 * @property-read Environment $environment
 * @property array<string, string> $checksums
 * @property array{modified: list<string>, added: list<string>, removed: list<string>}|null $changes
 * @property array<string, array{exit_code: int, ran_at: string}>|null $setup
 */
class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'environment_id',
        'application',
        'repository',
        'reference',
        'path',
        'commit',
        'manifest',
        'manifest_path',
        'checksums',
        'changes',
        'setup',
        'installed_at',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checksums' => 'array',
            'changes' => 'array',
            'setup' => 'array',
            'installed_at' => AsDateTime::class,
            'checked_at' => AsDateTime::class,
            'created_at' => AsDateTime::class,
            'updated_at' => AsDateTime::class,
        ];
    }

    protected static function newFactory(): InstallationFactory
    {
        return InstallationFactory::new();
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * The stored pods.yml, read with today's parser, or null when there is none or it no longer reads.
     */
    public function manifest(): ?ManifestData
    {
        if ($this->manifest === null) {
            return null;
        }

        try {
            return ManifestData::fromYaml($this->manifest, (string) $this->manifest_path);
        } catch (InvalidManifestException) {
            return null;
        }
    }

    /**
     * Remembers how a setup step of the stored pods.yml ended, so the application page can show what's left.
     */
    public function recordSetupStep(string $step, int $exitCode): void
    {
        $this->update([
            'setup' => [
                ...$this->setup ?? [],
                $step => ['exit_code' => $exitCode, 'ran_at' => now()->toIso8601String()],
            ],
        ]);
    }

    public function isChangedOutsidePods(): bool
    {
        return collect($this->changes ?? [])->flatten()->isNotEmpty();
    }

    /**
     * @param  iterable<array{name: string, content: string}>  $files
     * @return array<string, string>
     */
    public static function checksumsOf(iterable $files): array
    {
        $checksums = [];

        foreach ($files as $file) {
            $checksums[$file['name']] = hash('sha256', $file['content']);
        }

        ksort($checksums);

        return $checksums;
    }
}
