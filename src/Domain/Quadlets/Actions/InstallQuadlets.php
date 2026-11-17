<?php

declare(strict_types=1);

namespace Domain\Quadlets\Actions;

use Domain\Applications\Actions\TrackInstallation;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

class InstallQuadlets
{
    public function __construct(
        protected LpodClient $lpod,
        protected TrackInstallation $trackInstallation,
    ) {}

    /**
     * Creates the secrets the files need first, so the units find them when they start.
     * Secret values only travel on stdin to lpod; Pods never stores them. Pods does keep
     * the source and a checksum of each file, to tell when they're changed outside Pods.
     *
     * @param  list<array{name: string, content: string}>  $files
     * @param  list<array{name: string, driver: string, value: string}>  $secrets
     * @param  array{repository: string, reference: string, path: string, commit: ?string, manifest: ?string, manifest_path: ?string}|null  $source
     * @param  bool  $edit  whether these are edited files of the installed application, which keeps its other files
     */
    public function __invoke(
        Environment $environment,
        string $application,
        array $files,
        array $secrets = [],
        bool $start = true,
        bool $replace = false,
        ?array $source = null,
        bool $edit = false,
    ): void {
        foreach ($secrets as $secret) {
            $this->lpod->createSecret($environment, $secret['name'], $secret['driver'], $secret['value']);
        }

        $this->lpod->installQuadlets($environment, $application, $files, start: $start, replace: $replace);

        if ($edit) {
            $this->trackInstallation->edited($environment, $application, $files);

            return;
        }

        ($this->trackInstallation)($environment, $application, $files, $source);
    }
}
