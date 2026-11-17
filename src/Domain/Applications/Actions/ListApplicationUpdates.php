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
     * the tag it was installed at, newest first. Release candidates and other pre-releases are only
     * offered to an application installed at a pre-release. An application installed from a branch
     * (such as main) gets that branch, at its newest commit, when that isn't the one it has. Files
     * that didn't come from Git have none.
     *
     * @return list<GitTagData>
     *
     * @throws LpodException
     */
    public function __invoke(Installation $installation): array
    {
        if ($installation->repository === null || $installation->reference === null) {
            return [];
        }

        $current = GitTagData::versionOf($installation->reference);

        if ($current === null) {
            return $this->newerCommit($installation, $installation->repository, $installation->reference);
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

    /**
     * The branch at its newest commit, when the installed (short) commit isn't that one. A reference
     * that is neither a version nor a branch, such as a tag named stable, has no updates.
     *
     * @return list<GitTagData>
     *
     * @throws LpodException
     */
    protected function newerCommit(Installation $installation, string $repository, string $branch): array
    {
        $head = $this->lpod->branchHead($installation->environment, $repository, $branch);

        if ($head === null || (filled($installation->commit) && str_starts_with($head, $installation->commit))) {
            return [];
        }

        return [new GitTagData(name: $branch, commit: substr($head, 0, 7))];
    }
}
