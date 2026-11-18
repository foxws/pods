<?php

declare(strict_types=1);

namespace Domain\Environments\DataObjects;

use Domain\Environments\Models\Environment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The lpod on an environment's host, as it reported when Pods last verified it, next to the one this
 * Pods needs. A release older than that one, or a build from a branch, can be updated from Pods.
 */
#[TypeScript]
class LpodVersionData extends Data
{
    public const string ReleasePattern = '/^v?\d+\.\d+\.\d+([-+][0-9A-Za-z.-]+)?$/';

    public function __construct(
        public ?string $installed,
        public string $required,
        public bool $update_available,
    ) {}

    public static function fromEnvironment(Environment $environment): self
    {
        $installed = isset($environment->facts['lpod']) ? (string) $environment->facts['lpod'] : null;
        $required = (string) config('services.pods.lpod_version');

        return new self(
            installed: $installed,
            required: $required,
            update_available: $installed !== self::expected($required) && ! self::isNewerRelease($installed, $required),
        );
    }

    /**
     * What lpod --version reports once this version is installed: a release without its "v", or
     * dev-BRANCH for a branch.
     */
    public static function expected(string $version): string
    {
        if (preg_match(self::ReleasePattern, $version) === 1) {
            return ltrim($version, 'v');
        }

        return 'dev-'.str_replace('/', '-', $version);
    }

    /**
     * Whether the host already has a newer release than the one required, which isn't replaced by an older one.
     */
    protected static function isNewerRelease(?string $installed, string $required): bool
    {
        return $installed !== null
            && preg_match(self::ReleasePattern, $installed) === 1
            && preg_match(self::ReleasePattern, $required) === 1
            && version_compare($installed, ltrim($required, 'v'), '>');
    }
}
