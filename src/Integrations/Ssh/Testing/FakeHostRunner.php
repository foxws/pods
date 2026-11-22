<?php

declare(strict_types=1);

namespace Integrations\Ssh\Testing;

use Closure;
use Domain\Environments\Models\Environment;
use Illuminate\Support\Str;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\HostResult;
use PHPUnit\Framework\Assert;
use SensitiveParameter;

/**
 * Answers lpod commands with canned responses, and records what was run.
 */
class FakeHostRunner implements RunsHostCommands
{
    /**
     * @var list<array{environment: Environment, arguments: list<string>, input: ?string}>
     */
    protected array $recorded = [];

    /**
     * @param  array<string, HostResult|string>  $responses  Patterns of the joined arguments (e.g. "apps *") to results or JSON.
     */
    public function __construct(
        protected array $responses = [],
    ) {}

    public function respond(string $pattern, HostResult|string $response): self
    {
        $this->responses = [$pattern => $response] + $this->responses;

        return $this;
    }

    public function run(Environment $environment, array $arguments, #[SensitiveParameter] ?string $input = null, int $timeout = 30): HostResult
    {
        $this->recorded[] = compact('environment', 'arguments', 'input');

        return $this->respondTo($arguments);
    }

    /**
     * Writes the output of a successful response into the file, as the real runner streams it.
     */
    public function download(Environment $environment, array $arguments, string $path, int $timeout = 30): HostResult
    {
        $input = null;
        $this->recorded[] = compact('environment', 'arguments', 'input');

        $result = $this->respondTo($arguments);

        file_put_contents($path, $result->successful() ? $result->output : '');

        return new HostResult($result->exitCode, '', $result->errorOutput);
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function respondTo(array $arguments): HostResult
    {
        $command = implode(' ', $arguments);

        foreach ($this->responses as $pattern => $response) {
            if (Str::is($pattern, $command)) {
                return is_string($response) ? new HostResult(0, $response, '') : $response;
            }
        }

        return new HostResult(1, '', "No fake response for [lpod {$command}].");
    }

    /**
     * @param  list<string>|Closure(list<string>, ?string): bool  $expected
     */
    public function assertRan(array|Closure $expected): void
    {
        $matches = array_filter($this->recorded, fn (array $call): bool => $expected instanceof Closure
            ? $expected($call['arguments'], $call['input'])
            : $call['arguments'] === $expected);

        Assert::assertNotEmpty($matches, 'The expected lpod command was not run. Ran: '.json_encode(array_column($this->recorded, 'arguments')));
    }

    public function assertNothingRan(): void
    {
        Assert::assertEmpty($this->recorded, 'Unexpected lpod commands were run.');
    }

    /**
     * @return list<array{environment: Environment, arguments: list<string>, input: ?string}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
