<?php

declare(strict_types=1);

namespace Domain\Catalog\Actions;

use Domain\Catalog\DataObjects\CatalogData;
use Domain\Environments\Models\Environment;
use Integrations\Lpod\LpodClient;

/**
 * Reads the applications of every catalog repository in config/catalog.php on the host,
 * which clones them the way it fetches any other repository.
 */
class ListCatalog
{
    public function __construct(protected LpodClient $lpod) {}

    public function __invoke(Environment $environment): CatalogData
    {
        /** @var list<array{repository: string, reference: string}> $repositories */
        $repositories = config('catalog.repositories', []);

        return array_reduce(
            $repositories,
            fn (CatalogData $catalog, array $source): CatalogData => $catalog->merge(
                $this->lpod->catalog($environment, $source['repository'], $source['reference']),
            ),
            new CatalogData([], []),
        );
    }
}
