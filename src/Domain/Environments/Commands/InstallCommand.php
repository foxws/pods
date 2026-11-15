<?php

declare(strict_types=1);

namespace Domain\Environments\Commands;

use Domain\Environments\Actions\CreateEnvironment;
use Domain\Environments\Models\Environment;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Integrations\Ssh\Exceptions\SshException;
use Integrations\Ssh\SshIdentity;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class InstallCommand extends Command implements Isolatable
{
    /**
     * @var string
     */
    protected $signature = 'pods:install';

    /**
     * @var string
     */
    protected $description = 'Generate the SSH key and register the local host as an environment';

    public function handle(SshIdentity $identity, CreateEnvironment $createEnvironment): int
    {
        if (! $identity->exists()) {
            $identity->generate('pods@'.gethostname());

            info('Generated the SSH key of this Pods instance.');
        }

        note("Add this line to ~/.ssh/authorized_keys of every user Pods manages:\n\n{$identity->publicKey()}");

        $host = (string) config('services.pods.local.host');
        $port = (int) config('services.pods.local.port');
        $user = config('services.pods.local.user');

        if (! is_string($user) || $user === '') {
            note('Set PODS_LOCAL_SSH_USER to register the local host, or add environments in Pods.');

            return self::SUCCESS;
        }

        if (Environment::query()->where('is_local', true)->exists()) {
            info('The local environment is already registered.');

            return self::SUCCESS;
        }

        try {
            $environment = $createEnvironment('local', $host, $port, $user, isLocal: true);
        } catch (SshException $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        info("Registered the local environment ({$environment->target()}).");

        if ($this->call('pods:verify', ['environment' => $environment->ulid]) !== self::SUCCESS) {
            note('Run pods:verify once that is fixed.');
        }

        return self::SUCCESS;
    }
}
