<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Models\Installation;
use Domain\Quadlets\DataObjects\GitTagData;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class ListApplicationUpdates
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * The tags of the repository an application was installed from that name a newer version than
     * the tag it was installed at, newest first. An application installed from a branch (such as
     * main), or from files that didn't come from Git, has none. Release candidates and other
     * pre-releases are only offered to an application installed at a pre-release.
     *
     * @return list<GitTagData>
     *
     * @throws LpodException
     */
    public function __invoke(Installation $installation): array
    {
        $current = $installation->reference !== null ? GitTagData::versionOf($installation->reference) : null;

        if ($installation->repository === null || $current === null) {
            return [];
        }

        $allowsPrereleases = GitTagData::isPrerelease($current);

        $updates = array_filter(
            $this->lpod->tags($installation->environment, $installation->repository),
            function (GitTagData $tag) use ($current, $allowsPrereleases): bool {
                $version = $tag->version();

                return $version !== null
                    && ($allowsPrereleases || ! GitTagData::isPrerelease($version))
                    && version_compare($version, $current, '>');
            },
        );

        usort($updates, fn (GitTagData $a, GitTagData $b): int => version_compare((string) $b->version(), (string) $a->version()));

        return $updates;
    }
}
