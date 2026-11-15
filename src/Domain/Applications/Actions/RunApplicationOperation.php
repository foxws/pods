<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Enums\ApplicationOperation;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class RunApplicationOperation
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Queues the operation in systemd without waiting for it to finish.
     */
    public function __invoke(Environment $environment, string $application, ApplicationOperation $operation): void
    {
        match ($operation) {
            ApplicationOperation::Up => $this->lpod->up($environment, $application),
            ApplicationOperation::Down => $this->lpod->down($environment, $application),
            ApplicationOperation::Restart => $this->lpod->restart($environment, $application),
            ApplicationOperation::Redeploy => $this->lpod->redeploy($environment, $application),
        };
    }
}
