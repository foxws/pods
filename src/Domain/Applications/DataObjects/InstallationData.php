<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Domain\Applications\Models\Installation;
use Domain\Quadlets\DataObjects\ManifestData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where an application Pods installed came from, and what changed on the host since.
 */
#[TypeScript]
class InstallationData extends Data
{
    /**
     * @param  list<string>  $modified
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public ?string $repository,
        public ?string $reference,
        public ?string $path,
        public ?string $commit,
        public ?ManifestData $manifest,
        public bool $changed,
        public array $modified,
        public array $added,
        public array $removed,
        public ?string $installed_at,
        public ?string $checked_at,
    ) {}

    public static function fromModel(Installation $installation): self
    {
        return new self(
            repository: $installation->repository,
            reference: $installation->reference,
            path: $installation->path,
            commit: $installation->commit,
            manifest: $installation->manifest(),
            changed: $installation->isChangedOutsidePods(),
            modified: $installation->changes['modified'] ?? [],
            added: $installation->changes['added'] ?? [],
            removed: $installation->changes['removed'] ?? [],
            installed_at: $installation->installed_at?->toIso8601String(),
            checked_at: $installation->checked_at?->toIso8601String(),
        );
    }
}
