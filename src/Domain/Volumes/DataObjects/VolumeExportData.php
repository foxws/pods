<?php

declare(strict_types=1);

namespace Domain\Volumes\DataObjects;

use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class VolumeExportData extends Data
{
    public function __construct(
        public string $id,
        public string $volume,
        public VolumeExportStatus $status,
        public bool $stop_applications,
        public ?int $size,
        public ?string $error,
        public ?string $download_url,
        public string $created_at,
        public ?string $finished_at,
    ) {}

    public static function fromModel(VolumeExport $export): self
    {
        return new self(
            id: (string) $export->getRouteKey(),
            volume: $export->volume,
            status: $export->status,
            stop_applications: $export->stop_applications,
            size: $export->size,
            error: $export->error,
            download_url: $export->isDownloadable() ? static::downloadUrl($export) : null,
            created_at: $export->created_at->toDateTimeString(),
            finished_at: $export->finished_at?->toDateTimeString(),
        );
    }

    /**
     * A link that works for a day without anything else, like the one in a notification.
     */
    public static function downloadUrl(VolumeExport $export): string
    {
        return URL::temporarySignedRoute('actions.environments.volume-exports.download', now()->addDay(), [
            'environment' => $export->environment,
            'volumeExport' => $export,
        ]);
    }
}
