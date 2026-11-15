<?php

declare(strict_types=1);

namespace Domain\Environments\Commands;

use Domain\Environments\Actions\VerifyEnvironment;
use Domain\Environments\Models\Environment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Integrations\Lpod\Exceptions\LpodException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class VerifyCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pods:verify {environment? : The name or ULID of the environment, all of them when left out}';

    /**
     * @var string
     */
    protected $description = 'Connect to environments over SSH and check that lpod answers with a supported contract';

    public function handle(VerifyEnvironment $verifyEnvironment): int
    {
        $environments = $this->environments();

        if ($environments->isEmpty()) {
            warning('No environments found. Run pods:install, or add one in Pods.');

            return self::FAILURE;
        }

        $failed = $environments->reject(fn (Environment $environment): bool => $this->verify($verifyEnvironment, $environment));

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    protected function verify(VerifyEnvironment $verifyEnvironment, Environment $environment): bool
    {
        try {
            $facts = $verifyEnvironment($environment)->facts;
        } catch (LpodException $exception) {
            error("{$environment->name}: {$exception->getMessage()}");

            return false;
        }

        info(sprintf(
            '%s: lpod %s, Podman %s, systemd %s, %s as %s',
            $environment->name,
            $facts['lpod'] ?? '?',
            $facts['podman'] ?? '?',
            $facts['systemd'] ?? '?',
            $facts['scope'] ?? '?',
            $facts['user'] ?? $environment->user,
        ));

        return true;
    }

    /**
     * @return Collection<int, Environment>
     */
    protected function environments(): Collection
    {
        $search = $this->argument('environment');

        return Environment::query()
            ->when($search, fn ($query) => $query->where('name', $search)->orWhere('ulid', $search))
            ->orderBy('name')
            ->get();
    }
}
