<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class RemoveDropIn
{
    public function __construct(
        protected LpodClient $lpod,
        protected TrackInstallation $trackInstallation,
    ) {}

    /**
     * Removes a drop-in of one of the application's Quadlets. Running units keep it until
     * they restart. Pods forgets its checksum, so it doesn't count as removed outside Pods.
     *
     * @throws LpodException
     */
    public function __invoke(Environment $environment, string $application, string $name): void
    {
        $removed = $this->lpod->removeFiles($environment, $application, [$name]);

        $this->trackInstallation->removed($environment, $application, $removed);
    }
}
