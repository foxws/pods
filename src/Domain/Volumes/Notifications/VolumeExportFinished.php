<?php

declare(strict_types=1);

namespace Domain\Volumes\Notifications;

use Domain\Volumes\DataObjects\VolumeExportData;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the user who asked for an export that it's ready, with a download link, or why it failed.
 * The export's own row keeps the outcome, so this is only broadcast to their open pages.
 */
class VolumeExportFinished extends Notification
{
    public function __construct(
        public VolumeExport $export,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
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
