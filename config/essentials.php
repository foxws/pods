<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Foxws\Essentials\Configurables\AggressivePrefetching;
use Foxws\Essentials\Configurables\AutomaticallyEagerLoadRelationships;
use Foxws\Essentials\Configurables\EnforceMorphMap;
use Foxws\Essentials\Configurables\FakeSleep;
use Foxws\Essentials\Configurables\ForceSecurePassword;
use Foxws\Essentials\Configurables\ImmutableDates;
use Foxws\Essentials\Configurables\ModelShouldBeStrict;
use Foxws\Essentials\Configurables\PreventStrayRequests;
use Foxws\Essentials\Configurables\ProhibitDestructiveCommands;
use Foxws\Essentials\Configurables\ResourceWithoutWrapping;
use Foxws\Essentials\Configurables\SetTrustedProxies;
use Spatie\Tags\Tag;

return [

    /*
    |--------------------------------------------------------------------------
    | Configurable Features
    |--------------------------------------------------------------------------
    |
    | This array contains a list of configurable features that can be enabled
    | or disabled in your application.
    | Each feature is represented by a class that implements the Configurable interface.
    | You can add or remove features from this array as needed.
    |
    | ForceRootUrl is left out: Pods is reached on 127.0.0.1:PORT and on the
    | proxy's hostname, which can change in Pods after APP_URL was written,
    | so links and redirects keep the request's own origin.
    |
    | ForceHttpsScheme is left out too: Pods answers plain HTTP on
    | 127.0.0.1:PORT in production. Behind the proxy, links still get https
    | from the trusted X-Forwarded-Proto header.
    |
    */

    'configurables' => [
        AggressivePrefetching::class,
        AutomaticallyEagerLoadRelationships::class,
        EnforceMorphMap::class,
        FakeSleep::class,
        ForceSecurePassword::class,
        ImmutableDates::class,
        ModelShouldBeStrict::class,
        PreventStrayRequests::class,
        ProhibitDestructiveCommands::class,
        ResourceWithoutWrapping::class,
        SetTrustedProxies::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Whose X-Forwarded-For, -Port and -Proto headers Pods believes: addresses
    | or networks, separated by commas. The Pods proxy reaches it from a
    | private address, through Podman's network or the on-demand socket.
    | X-Forwarded-Host is never trusted.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', 'private_ranges'),

    /*
    |--------------------------------------------------------------------------
    | Model Morph Map
    |--------------------------------------------------------------------------
    |
    | This array defines the morph map for your Eloquent models.
    | The morph map allows you to customize the names used for polymorphic relationships.
    |
    */

    'morph_map' => [
        'tag' => Tag::class,
        'user' => User::class,
    ],

];
