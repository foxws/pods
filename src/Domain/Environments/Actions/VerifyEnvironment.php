<?php

declare(strict_types=1);

namespace Domain\Environments\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class VerifyEnvironment
{
    public function __construct(
        protected LpodClient $lpod,
    ) {}

    /**
     * Connects to the host and stores what lpod reports about it.
     */
    public function __invoke(Environment $environment): Environment
    {
        $environment->update([
            'facts' => $this->lpod->version($environment),
            'last_seen_at' => now(),
        ]);

        return $environment;
    }
}
