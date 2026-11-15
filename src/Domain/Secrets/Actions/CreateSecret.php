<?php

declare(strict_types=1);

namespace Domain\Secrets\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class CreateSecret
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Creates the secret, adds it to the given applications and, when asked, restarts them.
     * The value only travels on stdin to lpod; Pods never stores it.
     *
     * @param  list<array{application: string, type: string, target: string}>  $attachments
     */
    public function __invoke(
        Environment $environment,
        string $name,
        string $driver,
        string $value,
        array $attachments = [],
        bool $restart = false,
    ): void {
        $this->lpod->createSecret($environment, $name, $driver, $value);

        foreach ($attachments as $attachment) {
            $this->lpod->addSecretToApplication($environment, $attachment['application'], $name, $attachment['type'], $attachment['target']);
        }

        if (! $restart) {
            return;
        }

        foreach (array_unique(array_column($attachments, 'application')) as $application) {
            $this->lpod->restart($environment, $application);
        }
    }
}
