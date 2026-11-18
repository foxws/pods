<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Applications\Exceptions\CannotUpdateApplicationException;
use Domain\Applications\Models\Installation;
use Domain\Quadlets\Actions\InstallQuadlets;
use Domain\Secrets\DataObjects\SecretData;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class UpdateApplication
{
    public function __construct(
        protected LpodClient $lpod,
        protected InstallQuadlets $installQuadlets,
    ) {}

    /**
     * Installs the files of another tag of the repository an application came from, at the same path,
     * over its files. The new files are checked first, and need no secret the host lacks, so a refused
     * update leaves the application running. Running units only pick up new files when they start, so
     * every unit stops before the install starts them again.
     *
     * Files on the host the tag no longer has are removed when asked, before the install starts the
     * units again, but only the ones Pods installed: a drop-in added on the host stays. The files
     * that stay are returned too. The installation remembers what it was updated from, so the
     * application page can ask for the new release's after_update steps.
     *
     * @return array{removed: list<string>, kept: list<string>} the application's files the tag doesn't have
     *
     * @throws CannotUpdateApplicationException
     * @throws LpodException
     */
    public function __invoke(Installation $installation, string $tag, bool $removeOldFiles = true): array
    {
        $environment = $installation->environment;
        $application = $installation->application;
        $path = (string) $installation->path;
        $updatedFrom = $installation->reference === $tag ? $installation->commit : $installation->reference;

        if ($installation->repository === null) {
            throw CannotUpdateApplicationException::notFromGit($application);
        }

        $source = $this->lpod->fetchQuadlets($environment, $installation->repository, $tag, $path);

        if ($source->files === []) {
            throw CannotUpdateApplicationException::noFiles($tag, $path);
        }

        $check = $this->lpod->checkQuadlets($environment, $source->files);

        if (! $check->valid) {
            throw CannotUpdateApplicationException::invalidFiles($tag, $check->errors);
        }

        $missingSecrets = $this->missingSecrets($installation, $source->files);

        if ($missingSecrets !== []) {
            throw CannotUpdateApplicationException::missingSecrets($tag, $missingSecrets);
        }

        $leftOver = array_values(array_diff($this->installedFileNames($installation), array_column($source->files, 'name')));
        $removable = $removeOldFiles ? array_values(array_intersect($leftOver, array_keys($installation->checksums))) : [];

        $this->lpod->stopAndWait($environment, $application);

        $removed = $removable !== [] ? $this->lpod->removeFiles($environment, $application, $removable) : [];

        ($this->installQuadlets)($environment, $application, $source->files, start: true, replace: true, source: [
            'repository' => $installation->repository,
            'reference' => $tag,
            'path' => $path,
            'commit' => $source->commit,
            'manifest' => $source->manifest_yaml,
            'manifest_path' => $source->manifest_path,
        ]);

        $installation->refresh()->update(['updated_from' => $updatedFrom]);

        return ['removed' => $removed, 'kept' => array_values(array_diff($leftOver, $removed))];
    }

    /**
     * The secrets the files reference with Secret= that the host doesn't have.
     *
     * @param  list<array{name: string, content: string}>  $files
     * @return list<string>
     */
    protected function missingSecrets(Installation $installation, array $files): array
    {
        $referenced = [];

        foreach ($files as $file) {
            preg_match_all('/^Secret=([^,\s]+)/m', $file['content'], $matches);
            array_push($referenced, ...$matches[1]);
        }

        $onHost = array_map(
            fn (SecretData $secret): string => $secret->name,
            array_filter($this->lpod->secrets($installation->environment), fn (SecretData $secret): bool => ! $secret->missing),
        );

        return array_values(array_unique(array_diff($referenced, $onHost)));
    }

    /**
     * The application's files on the host, without the drop-ins lpod writes itself.
     *
     * @return list<string>
     */
    protected function installedFileNames(Installation $installation): array
    {
        return array_values(array_map(
            fn (QuadletFileData $file): string => $file->name,
            array_filter(
                $this->lpod->files($installation->environment, $installation->application),
                fn (QuadletFileData $file): bool => preg_match(SyncInstallation::ManagedDropInPattern, $file->name) !== 1,
            ),
        ));
    }
}
