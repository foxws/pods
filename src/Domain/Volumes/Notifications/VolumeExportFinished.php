<?php

declare(strict_types=1);

namespace Domain\Volumes\Notifications;

use Domain\Users\Enums\NotificationLevel;
use Domain\Volumes\DataObjects\VolumeExportData;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the user who asked for an export that it's ready, with a download link, or why it failed.
 * It's kept for the notifications page and broadcast to their open pages as a toast.
 */
class VolumeExportFinished extends Notification
{
    public function __construct(
        public VolumeExport $export,
    ) {}

    /**
     * The row is stored first, so the pages the broadcast reloads already list it.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{title: string, message: string, level: string, url: string, environment: string}
     */
    public function toDatabase(object $notifiable): array
    {
        $export = $this->export;
        $environment = $export->environment;

        return [
            'title' => $export->status === VolumeExportStatus::Completed
                ? __(':volume is exported', ['volume' => $export->volume])
                : __(':volume wasn\'t exported', ['volume' => $export->volume]),
            'message' => $export->status === VolumeExportStatus::Completed
                ? __('The archive from :environment is ready to download on the Volumes page.', ['environment' => $environment->name])
                : ($export->error ?? __('The export from :environment failed.', ['environment' => $environment->name])),
            'level' => $export->status === VolumeExportStatus::Completed ? NotificationLevel::Success->value : NotificationLevel::Error->value,
            // The Volumes page, since the export's own download link only works for a day.
            'url' => route('environments.volumes.index', $environment, absolute: false),
            'environment' => $environment->name,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'environment' => $this->export->environment->name,
            'export' => VolumeExportData::fromModel($this->export)->toArray(),
        ];
    }

    public function broadcastType(): string
    {
        return 'volume-export.finished';
    }
}
