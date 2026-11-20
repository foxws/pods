<?php

declare(strict_types=1);

namespace Domain\Volumes\Models;

use Carbon\CarbonInterface;
use Database\Factories\VolumeExportFactory;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Domain\Volumes\Enums\VolumeExportStatus;
use Foxws\Essentials\Casts\AsDateTime;
use Foxws\Essentials\Concerns\HasUlid;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An archive of a volume's contents that Pods streamed from the host onto its own backups
 * disk, in a queued job. Only its row knows where the file is and how the export went.
 *
 * @property-read Environment $environment
 * @property-read User|null $user
 * @property VolumeExportStatus $status
 * @property CarbonInterface $created_at
 */
#[RouteKey('ulid')]
class VolumeExport extends Model
{
    /** @use HasFactory<VolumeExportFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'environment_id',
        'user_id',
        'volume',
        'stop_applications',
        'status',
        'path',
        'size',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'stop_applications' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => VolumeExportStatus::class,
            'stop_applications' => 'boolean',
            'size' => 'integer',
            'started_at' => AsDateTime::class,
            'finished_at' => AsDateTime::class,
            'created_at' => AsDateTime::class,
            'updated_at' => AsDateTime::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $export): void {
            $export->deleteArchive();
        });
    }

    protected static function newFactory(): VolumeExportFactory
    {
        return VolumeExportFactory::new();
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('backups');
    }

    /**
     * Where the archive goes on the backups disk: a folder per host and volume.
     */
    public function archivePath(): string
    {
        return "{$this->environment->ulid}/{$this->volume}/{$this->ulid}.tar";
    }

    /**
     * The name a download is saved as.
     */
    public function fileName(): string
    {
        return "{$this->volume}-{$this->created_at->format('Y-m-d-His')}.tar";
    }

    public function isDownloadable(): bool
    {
        return $this->status === VolumeExportStatus::Completed && $this->path !== null;
    }

    public function deleteArchive(): void
    {
        if ($this->path !== null) {
            static::disk()->delete($this->path);
        }
    }
}
