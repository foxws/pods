<?php

declare(strict_types=1);

namespace Domain\Users\DataObjects;

use Domain\Users\Enums\NotificationLevel;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A stored notification, in the shape every notification's toDatabase() gives:
 * a title, a message, a level and a link to what it's about.
 */
#[TypeScript]
class NotificationData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $message,
        public NotificationLevel $level,
        public ?string $url,
        public ?string $environment,
        public ?string $read_at,
        public string $created_at,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            type: class_basename($notification->type),
            title: (string) ($data['title'] ?? class_basename($notification->type)),
            message: isset($data['message']) ? (string) $data['message'] : null,
            level: NotificationLevel::tryFrom((string) ($data['level'] ?? '')) ?? NotificationLevel::Info,
            url: isset($data['url']) ? (string) $data['url'] : null,
            environment: isset($data['environment']) ? (string) $data['environment'] : null,
            read_at: $notification->read_at?->toDateTimeString(),
            created_at: $notification->created_at->toDateTimeString(),
        );
    }
}
