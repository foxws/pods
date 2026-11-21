<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Applications\Models\Installation;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class SyncInstallation
{
    /**
     * Drop-ins lpod writes itself, such as lpod-ondemand.conf, aren't changes outside Pods.
     */
    public const string ManagedDropInPattern = '#\.d/lpod-[^/]+\.conf$#';

    /**
     * Drop-ins that belong to the host, such as memory.local.conf, aren't either: they're the owner's
     * own overrides, which Pods writes when asked but never tracks, and which updates leave alone.
     */
    public const string LocalDropInPattern = '#\.d/[^/]+\.local\.conf$#';

    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Compares the files on the host with the checksums of the files Pods installed, and
     * keeps what was modified, added or removed since. The files on the host stay as they are.
     *
     * @param  list<QuadletFileData>|null  $files  the application's files on the host, read when not given
     *
     * @throws LpodException
     */
    public function __invoke(Installation $installation, ?array $files = null): Installation
    {
        $files ??= $this->lpod->files($installation->environment, $installation->application);

        $current = self::checksumsOnHost($files);
        $installed = $installation->checksums;

        $changes = [
            'modified' => array_keys(array_filter(
                array_intersect_key($current, $installed),
                fn (string $checksum, string $name): bool => $checksum !== $installed[$name],
                ARRAY_FILTER_USE_BOTH,
            )),
            'added' => array_keys(array_diff_key($current, $installed)),
            'removed' => array_keys(array_diff_key($installed, $current)),
        ];

        $installation->update([
            'changes' => collect($changes)->flatten()->isEmpty() ? null : $changes,
            'checked_at' => now(),
        ]);

        return $installation;
    }

    /**
     * @param  list<QuadletFileData>  $files
     * @return array<string, string>
     */
    public static function checksumsOnHost(array $files): array
    {
        $files = array_filter($files, fn (QuadletFileData $file): bool => self::isTracked($file->name));

        return Installation::checksumsOf(array_map(fn (QuadletFileData $file): array => [
            'name' => $file->name,
            'content' => $file->content,
        ], $files));
    }

    /**
     * Whether Pods keeps a checksum of the file: not for lpod's own drop-ins, nor local ones.
     */
    public static function isTracked(string $name): bool
    {
        return preg_match(self::ManagedDropInPattern, $name) !== 1 && preg_match(self::LocalDropInPattern, $name) !== 1;
    }
}
