<?php

declare(strict_types=1);

namespace Domain\Environments\Models;

use Database\Factories\EnvironmentFactory;
use Domain\Applications\Models\Installation;
use Domain\Applications\Models\PinnedApplication;
use Domain\Volumes\Models\VolumeExport;
use Foxws\Essentials\Casts\AsDateTime;
use Foxws\Essentials\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array{contract: int, lpod: string, podman: string, systemd: string, scope: string, user: string, hostname: string}|null $facts
 */
#[RouteKey('ulid')]
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'host',
        'port',
        'user',
        'host_key',
        'is_local',
        'facts',
        'last_seen_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'host_key',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_local' => 'boolean',
            'facts' => 'array',
            'last_seen_at' => AsDateTime::class,
            'created_at' => AsDateTime::class,
            'updated_at' => AsDateTime::class,
        ];
    }

    protected static function newFactory(): EnvironmentFactory
    {
        return EnvironmentFactory::new();
    }

    /**
     * @return HasMany<Installation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class)->chaperone();
    }

    /**
     * @return HasMany<PinnedApplication, $this>
     */
    public function pinnedApplications(): HasMany
    {
        return $this->hasMany(PinnedApplication::class)->chaperone();
    }

    /**
     * @return HasMany<VolumeExport, $this>
     */
    public function volumeExports(): HasMany
    {
        return $this->hasMany(VolumeExport::class)->chaperone();
    }

    public function target(): string
    {
        return "{$this->user}@{$this->host}";
    }
}
