<?php

declare(strict_types=1);

namespace Domain\Secrets\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class ReplaceSecret
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Rotates the value, then restarts the given applications so their containers read it.
     *
     * @param  list<string>  $restart
     */
    public function __invoke(Environment $environment, string $name, string $driver, string $value, array $restart = []): void
    {
        $this->lpod->createSecret($environment, $name, $driver, $value, replace: true);

        foreach ($restart as $application) {
            $this->lpod->restart($environment, $application);
        }
    }
}
