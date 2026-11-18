<?php

declare(strict_types=1);

namespace Domain\Environments\Actions;

use Domain\Environments\DataObjects\LpodVersionData;
use Domain\Environments\Exceptions\CannotUpdateLpodException;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class UpdateLpod
{
    public function __construct(
        protected LpodClient $lpod,
        protected VerifyEnvironment $verifyEnvironment,
    ) {}

    /**
     * Installs the lpod this Pods needs on the host, for the user Pods connects as, since each user has
     * their own lpod. The host is verified afterwards, so the version it reports is the one stored,
     * and an update that didn't take is caught even when lpod's self-update ended without error.
     *
     * @throws CannotUpdateLpodException
     * @throws LpodException
     */
    public function __invoke(Environment $environment): Environment
    {
        $version = (string) config('services.pods.lpod_version');

        $this->lpod->selfUpdate($environment, $version);

        $environment = ($this->verifyEnvironment)($environment);
        $installed = $environment->facts['lpod'] ?? null;

        if ($installed !== LpodVersionData::expected($version)) {
            throw CannotUpdateLpodException::stillAt($installed, $version);
        }

        return $environment;
    }
}
