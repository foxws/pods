<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Exceptions\CannotLinkApplicationSourceException;
use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class LinkApplicationSource
{
    public function __construct(
        protected LpodClient $lpod,
        protected SyncInstallation $syncInstallation,
    ) {}

    /**
     * Remembers the Git source an application's files came from, such as one installed outside Pods,
     * so Pods can update it. The checksums are the source's, so the files on the host that differ from
     * it show up as changed outside Pods, until they're kept or replaced with the source's. The files
     * on the host don't change, and how its setup steps ended stays.
     *
     * @throws CannotLinkApplicationSourceException
     * @throws LpodException
     */
    public function __invoke(Environment $environment, string $application, string $repository, string $reference, string $path = ''): Installation
    {
        $files = $this->lpod->files($environment, $application);
        $source = $this->lpod->fetchQuadlets($environment, $repository, $reference, $path);

        if ($source->files === []) {
            throw CannotLinkApplicationSourceException::noFiles($reference, $path);
        }

        $installation = Installation::query()->updateOrCreate([
            'environment_id' => $environment->getKey(),
            'application' => $application,
        ], [
            'repository' => $repository,
            'reference' => $reference,
            'path' => $path,
            'commit' => $source->commit,
            'manifest' => $source->manifest_yaml,
            'manifest_path' => $source->manifest_path,
            'checksums' => Installation::checksumsOf($source->files),
            'updated_from' => null,
        ]);

        return ($this->syncInstallation)($installation, $files);
    }
}
