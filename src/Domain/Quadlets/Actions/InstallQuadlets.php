<?php

declare(strict_types=1);

namespace Domain\Quadlets\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class InstallQuadlets
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Creates the secrets the files need first, so the units find them when they start.
     * Secret values only travel on stdin to lpod; Pods never stores them.
     *
     * @param  list<array{name: string, content: string}>  $files
     * @param  list<array{name: string, driver: string, value: string}>  $secrets
     */
    public function __invoke(
        Environment $environment,
        string $application,
        array $files,
        array $secrets = [],
        bool $start = true,
        bool $replace = false,
    ): void {
        foreach ($secrets as $secret) {
            $this->lpod->createSecret($environment, $secret['name'], $secret['driver'], $secret['value']);
        }

        $this->lpod->installQuadlets($environment, $application, $files, start: $start, replace: $replace);
    }
}
