<?php

declare(strict_types=1);

namespace Modules\Web\Containers\Props;

use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;

/**
 * The props of a container's page: its inspection first, then its logs and Quadlet, and its
 * stats only when the page polls for them.
 */
class ContainerShowProps implements ProvidesInertiaProperties
{
    use ReadsFromHost;

    protected Environment $environment;

    protected string $container;

    public function __construct(
        protected LpodClient $lpod,
    ) {}

    public function for(Environment $environment, string $container): self
    {
        $this->environment = $environment;
        $this->container = $container;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'environment' => EnvironmentData::fromModel($this->environment),
            'name' => $this->container,
            'container' => $this->deferFromHost(fn () => $this->lpod->container($this->environment, $this->container)),
            'logs' => $this->deferFromHost(fn (): array => $this->lpod->containerLogs($this->environment, $this->container), 'details'),
            'stats' => $this->optionalFromHost(fn () => $this->lpod->containerStats($this->environment, $this->container)),
            'quadlet' => $this->deferFromHost(fn (): ?QuadletFileData => $this->quadlet(), 'details'),
        ];
    }

    /**
     * The Quadlet file that defines the container, or null when it was started outside Quadlet.
     */
    protected function quadlet(): ?QuadletFileData
    {
        $summary = $this->lpod->container($this->environment, $this->container)->container;

        if ($summary->quadlet === null || $summary->application === null) {
            return null;
        }

        return collect($this->lpod->files($this->environment, $summary->application))
            ->firstWhere('name', $summary->quadlet);
    }
}
