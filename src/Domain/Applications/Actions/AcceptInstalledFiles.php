<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class AcceptInstalledFiles
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Takes the application's files on the host as they are now, after they were changed outside
     * Pods, or to start tracking an application that was installed outside Pods. A known source stays.
     *
     * @throws LpodException
     */
    public function __invoke(Environment $environment, string $application): Installation
    {
        $files = $this->lpod->files($environment, $application);

        return Installation::query()->updateOrCreate([
            'environment_id' => $environment->getKey(),
            'application' => $application,
        ], [
            'checksums' => SyncInstallation::checksumsOnHost($files),
            'changes' => null,
            'checked_at' => now(),
        ]);
    }
}
