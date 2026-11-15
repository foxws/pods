<?php

declare(strict_types=1);

namespace Integrations\Ssh\Contracts;

use Domain\Environments\Models\Environment;
use Integrations\Ssh\HostResult;

interface RunsHostCommands
{
    /**
     * Runs lpod with the given arguments on the environment's host. The input is
     * passed on stdin, so values such as secrets never appear in an argument list.
     *
     * @param  list<string>  $arguments
     */
    public function run(Environment $environment, array $arguments, ?string $input = null, int $timeout = 30): HostResult;
}
