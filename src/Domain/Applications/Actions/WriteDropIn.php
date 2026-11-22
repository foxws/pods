<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
use SensitiveParameter;

class WriteDropIn
{
    public function __construct(
        protected LpodClient $lpod,
        protected TrackInstallation $trackInstallation,
    ) {}

    /**
     * Adds or replaces a drop-in of one of the application's Quadlets. Running units only pick it
     * up when they restart. A drop-in Pods tracks gets its new checksum, like an edited Quadlet;
     * a local one (NAME.local.conf) stays the host's own.
     *
     * @throws LpodException
     */
    public function __invoke(Environment $environment, string $application, string $name, #[SensitiveParameter] string $content): QuadletFileData
    {
        $file = $this->lpod->writeFile($environment, $application, $name, $content);

        $this->trackInstallation->edited($environment, $application, [['name' => $file->name, 'content' => $file->content]]);

        return $file;
    }
}
