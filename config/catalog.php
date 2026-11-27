<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Catalog
|--------------------------------------------------------------------------
|
| The repositories the Install application page offers applications from
| are settings (Foundation\Settings\CatalogSettings), edited under
| Settings, Catalog. Only what's pinned to a release of Pods is kept here.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Pods Proxy
    |--------------------------------------------------------------------------
    |
    | Where the Proxy section of a host's settings installs the Pods proxy
    | from, as the application pods-proxy: Caddy, as plain Quadlet files.
    |
    */

    'proxy' => [
        'repository' => 'https://github.com/foxws/pods',
        'reference' => 'v1.5.1',
        'path' => 'containers/proxy',
    ],

];
