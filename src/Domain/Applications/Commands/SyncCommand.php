<?php

declare(strict_types=1);

namespace Domain\Applications\Commands;

use Domain\Applications\Actions\SyncInstallation;
use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pods:sync {environment? : The name or ULID of the environment, all of them when left out}';

    /**
     * @var string
     */
    protected $description = 'Check the applications Pods installed for files changed or removed outside Pods, and forget pins of removed applications';

    public function handle(LpodClient $lpod, SyncInstallation $syncInstallation): int
    {
        $failed = $this->environments()->reject(fn (Environment $environment): bool => $this->sync($lpod, $syncInstallation, $environment));

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    protected function sync(LpodClient $lpod, SyncInstallation $syncInstallation, Environment $environment): bool
    {
        try {
            $applications = array_map(fn (ApplicationData $application): string => $application->name, $lpod->applications($environment));

            $unpinned = $environment->pinnedApplications()->whereNotIn('application', $applications)->delete();

            if ($unpinned > 0) {
                info("{$environment->name}: {$unpinned} pins of removed applications forgotten.");
            }

            $checked = 0;

            foreach ($environment->installations as $installation) {
                if (! in_array($installation->application, $applications, true)) {
                    $installation->delete();

                    warning("{$environment->name}: {$installation->application} was removed outside Pods.");

                    continue;
                }

                $checked++;

                if ($syncInstallation($installation)->isChangedOutsidePods()) {
                    warning("{$environment->name}: {$installation->application} has files changed outside Pods: ".$this->describe($installation).'.');
                }
            }
        } catch (LpodException $exception) {
            error("{$environment->name}: {$exception->getMessage()}");

            return false;
        }

        info("{$environment->name}: {$checked} applications checked.");

        return true;
    }

    protected function describe(Installation $installation): string
    {
        return collect($installation->changes ?? [])
            ->filter()
            ->map(fn (array $names, string $change): string => "{$change} ".implode(', ', $names))
            ->implode('; ');
    }

    /**
     * @return Collection<int, Environment>
     */
    protected function environments(): Collection
    {
        $search = $this->argument('environment');

        return Environment::query()
            ->where(fn ($query) => $query->has('installations')->orHas('pinnedApplications'))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', $search)->orWhere('ulid', $search)))
            ->with('installations')
            ->orderBy('name')
            ->get();
    }
}
