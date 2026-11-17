<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Models\Installation;
use Domain\Containers\DataObjects\ContainerExecResultData;
use Domain\Containers\DataObjects\HostContainerData;
use Domain\Quadlets\DataObjects\ManifestSetupStepData;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class RunSetupStep
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Runs a setup step of the stored pods.yml in its container, and remembers how it ended.
     * The step names the container by its Quadlet, as on-demand's idle check does, so Pods
     * looks up the name Podman gave it (systemd-NAME, or the Quadlet's ContainerName=).
     *
     * @param  array<string, string>  $values  the step's inputs
     *
     * @throws LpodException when the container isn't there, or lpod couldn't run the command
     */
    public function __invoke(Installation $installation, ManifestSetupStepData $step, array $values): ContainerExecResultData
    {
        $environment = $installation->environment;

        $container = collect($this->lpod->containers($environment))->first(
            fn (HostContainerData $container): bool => $container->application === $installation->application
                && $container->quadlet === "{$step->container}.container",
        );

        if ($container === null) {
            throw LpodException::containerMissing($installation->application, $step->container);
        }

        $result = $this->lpod->exec(
            $environment,
            $container->name,
            $step->arguments($values),
            input: $step->stdin($values),
            user: $step->user,
            workdir: $step->workdir,
        );

        $installation->recordSetupStep($step->name, $result->exit_code);

        return $result;
    }
}
