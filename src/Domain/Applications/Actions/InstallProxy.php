<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Environments\Models\Environment;
use Domain\Quadlets\Actions\InstallQuadlets;
use Integrations\Lpod\LpodClient;

class InstallProxy
{
    /**
     * The application the Pods proxy is installed as, which lpod's proxy commands look for.
     */
    public const string Application = 'pods-proxy';

    public function __construct(
        protected LpodClient $lpod,
        protected InstallQuadlets $installQuadlets,
    ) {}

    /**
     * Fetches the proxy's Quadlets from Pods' repository and installs them like any application,
     * so Pods keeps their source. The units don't start yet: the settings decide the ports first.
     */
    public function __invoke(Environment $environment): void
    {
        $repository = (string) config('catalog.proxy.repository');
        $reference = (string) config('catalog.proxy.reference');
        $path = (string) config('catalog.proxy.path');

        $source = $this->lpod->fetchQuadlets($environment, $repository, $reference, $path);

        ($this->installQuadlets)(
            environment: $environment,
            application: self::Application,
            files: $source->files,
            start: false,
            source: [
                'repository' => $repository,
                'reference' => $reference,
                'path' => $path,
                'commit' => $source->commit,
                'manifest' => null,
                'manifest_path' => null,
            ],
        );
    }
}
