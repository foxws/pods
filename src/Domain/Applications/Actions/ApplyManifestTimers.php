<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\ManifestData;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class ApplyManifestTimers
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Sets up the timers the application's pods.yml names, after installing or updating it, so the
     * host has the ones its files came with. A pods.yml without timers removes the ones the previous
     * pods.yml set up; when neither has any, the host isn't asked, so a host whose lpod has no timers
     * command still installs everything else.
     *
     * @return bool whether the host was asked
     *
     * @throws LpodException
     */
    public function __invoke(Environment $environment, string $application, ?ManifestData $manifest, ?ManifestData $previous = null): bool
    {
        $timers = $manifest->timers ?? [];

        if ($timers === [] && ($previous->timers ?? []) === []) {
            return false;
        }

        $this->lpod->setTimers($environment, $application, $timers);

        return true;
    }
}
