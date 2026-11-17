<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Catalog
|--------------------------------------------------------------------------
|
| Git repositories the Install application page offers applications from.
| Every directory at a repository's root with a pods.yml is one application,
| described by that pods.yml (its label, description, author, icon, notes
| and sources). The host clones the repository, like any other fetch.
|
*/

return [

    'repositories' => [
        [
            'repository' => 'https://github.com/foxws/quadlets',
            'reference' => 'main',
        ],
    ],

];
