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

    /**
     * Runs lpod and streams its output into the file at the path instead of keeping it, for
     * output such as an archive that's too large to hold in memory. The result's output is
     * empty; its error output is lpod's.
     *
     * @param  list<string>  $arguments
     */
    public function download(Environment $environment, array $arguments, string $path, int $timeout = 30): HostResult;
}
