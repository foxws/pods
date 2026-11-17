<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A tag of a Git repository, and the commit it points at.
 */
#[TypeScript]
class GitTagData extends Data
{
    public function __construct(
        public string $name,
        public string $commit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            commit: (string) ($payload['commit'] ?? ''),
        );
    }

    /**
     * The version the tag names, without a leading "v", or null when it doesn't name one (such as "latest").
     */
    public function version(): ?string
    {
        return self::versionOf($this->name);
    }

    public static function versionOf(string $reference): ?string
    {
        if (preg_match('/^v?(\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.-]+)?)$/', $reference, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public static function isPrerelease(string $version): bool
    {
        return str_contains(explode('+', $version)[0], '-');
    }
}
