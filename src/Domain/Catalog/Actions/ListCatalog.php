<?php

declare(strict_types=1);

namespace Domain\Catalog\Actions;

use Domain\Catalog\DataObjects\CatalogData;
use Domain\Environments\Models\Environment;
use Foundation\Settings\CatalogSettings;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

/**
 * Reads the applications of every catalog repository in the settings on the host, which clones
 * them the way it fetches any other repository. One that can't be read doesn't hide the others.
 */
class ListCatalog
{
    /**
     * Seconds for all repositories together, under the 90 seconds a request may take.
     */
    protected const int TimeBudget = 75;

    public function __construct(
        protected LpodClient $lpod,
        protected CatalogSettings $settings,
    ) {}

    /**
     * @throws LpodException when none of the repositories could be read
     */
    public function __invoke(Environment $environment): CatalogData
    {
        $repositories = $this->settings->repositories;
        $count = count($repositories);
        $deadline = now()->addSeconds(self::TimeBudget);

        $catalog = new CatalogData([], []);
        $failures = [];

        foreach ($repositories as $index => ['repository' => $repository, 'reference' => $reference]) {
            // An equal share of the time that's left, so a hanging clone can't use up the others' time.
            $timeout = max(1, (int) ceil(now()->diffInSeconds($deadline, absolute: false) / ($count - $index)));

            try {
                $read = $this->lpod->catalog($environment, $repository, $reference, $timeout);
            } catch (LpodException $exception) {
                report($exception);
                $failures[] = $exception->getMessage();

                continue;
            } catch (ProcessTimedOutException) {
                // Not reported: its message is the whole ssh command.
                $failures[] = LpodException::catalogTimedOut($repository, $timeout)->getMessage();

                continue;
            }

            $catalog = $catalog->merge($count > 1 ? $read->withSourceInErrors($repository) : $read);
        }

        if ($count > 0 && count($failures) === $count) {
            throw LpodException::catalogUnavailable($failures);
        }

        $catalog->failures = $failures;

        return $catalog;
    }
}
