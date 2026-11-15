<?php

declare(strict_types=1);

namespace Domain\Environments\Actions;

use Domain\Environments\Models\Environment;
use Integrations\Ssh\HostKeyScanner;

class CreateEnvironment
{
    public function __construct(
        protected HostKeyScanner $scanner,
    ) {}

    /**
     * Pins the host's current key, so every later connection fails if it changes.
     */
    public function __invoke(string $name, string $host, int $port, string $user, bool $isLocal = false): Environment
    {
        return Environment::create([
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'host_key' => $this->scanner->scan($host, $port),
            'is_local' => $isLocal,
        ]);
    }
}
