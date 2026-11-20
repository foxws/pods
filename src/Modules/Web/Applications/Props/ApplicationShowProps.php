<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Props;

use Domain\Applications\Actions\ListApplicationUpdates;
use Domain\Applications\Actions\SuggestOnDemandSettings;
use Domain\Applications\Actions\SuggestRoutes;
use Domain\Applications\Actions\SyncInstallation;
use Domain\Applications\DataObjects\InstallationData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Applications\DataObjects\TimerData;
use Domain\Applications\Models\Installation;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;

/**
 * The props of an application's page. Most are read from the host after the page renders,
 * in groups that load in parallel: the status first, then the details (with the timers), the routes and the updates.
 */
class ApplicationShowProps implements ProvidesInertiaProperties
{
    use ReadsFromHost;

    protected Environment $environment;

    protected string $application;

    /**
     * @var list<QuadletFileData>|null
     */
    protected ?array $files = null;

    protected Installation|false|null $installation = null;

    public function __construct(
        protected LpodClient $lpod,
        protected SuggestOnDemandSettings $suggestOnDemandSettings,
        protected SyncInstallation $syncInstallation,
        protected ListApplicationUpdates $listApplicationUpdates,
        protected SuggestRoutes $suggestRoutes,
    ) {}

    public function for(Environment $environment, string $application): self
    {
        $this->environment = $environment;
        $this->application = $application;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'environment' => EnvironmentData::fromModel($this->environment),
            'name' => $this->application,
            'application' => $this->deferFromHost(fn () => $this->lpod->application($this->environment, $this->application), live: $this->environment),
            'ondemand' => $this->deferFromHost(fn () => $this->lpod->onDemand($this->environment, $this->application), live: $this->environment),
            'ondemandSuggestion' => $this->deferFromHost(fn () => ($this->suggestOnDemandSettings)(
                $this->lpod->application($this->environment, $this->application)->application,
                $this->files(),
                $this->installation()?->manifest(),
            ), 'details'),
            'files' => $this->deferFromHost(fn (): array => $this->files(), 'details', live: $this->environment),
            'installation' => $this->deferFromHost(fn (): ?InstallationData => $this->installationData(), 'details', live: $this->environment),
            'routes' => $this->deferFromHost(fn () => ($this->suggestRoutes)(
                $this->application,
                $this->lpod->proxyRoutes($this->environment),
                $this->installation()?->manifest(),
            ), 'routes', live: $this->environment),
            'timers' => $this->deferFromHost(fn (): array => $this->timers(), 'details', live: $this->environment),
            'updates' => $this->deferFromHost(fn (): array => $this->installation() !== null ? ($this->listApplicationUpdates)($this->installation()) : [], 'updates'),
            'logs' => $this->deferFromHost(fn (): array => $this->lpod->logs($this->environment, $this->application), 'details'),
            'secrets' => $this->deferFromHost(fn (): array => $this->lpod->secrets($this->environment), 'details', live: $this->environment),
        ];
    }

    /**
     * The application's files, read once for the details that need them.
     *
     * @return list<QuadletFileData>
     */
    protected function files(): array
    {
        return $this->files ??= $this->lpod->files($this->environment, $this->application);
    }

    /**
     * The installation Pods keeps of the application, if it installed it.
     */
    protected function installation(): ?Installation
    {
        if ($this->installation === null) {
            $this->installation = $this->environment->installations()->firstWhere('application', $this->application) ?? false;
        }

        return $this->installation ?: null;
    }

    /**
     * The application's timers. Pods sets them up from its pods.yml, so only an application whose
     * stored pods.yml names timers asks the host, which keeps the page working on a host whose
     * lpod has no timers command.
     *
     * @return list<TimerData>
     */
    protected function timers(): array
    {
        if (($this->installation()?->manifest()->timers ?? []) === []) {
            return [];
        }

        return $this->lpod->timers($this->environment, $this->application);
    }

    /**
     * The installation, compared with the files on the host first.
     */
    protected function installationData(): ?InstallationData
    {
        $installation = $this->installation();

        return $installation !== null ? InstallationData::fromModel(($this->syncInstallation)($installation, $this->files())) : null;
    }
}
