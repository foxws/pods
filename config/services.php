<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'pods' => [
        'lpod_binary' => env('PODS_LPOD_BINARY', 'lpod'),
        // The lpod release this Pods needs, which Update lpod installs; keep it the LPOD_REF of install.sh. A branch such as main installs that branch.
        'lpod_version' => env('PODS_LPOD_VERSION', 'v4.0.0-rc.5'),
        'cache_ttl' => (int) env('PODS_CACHE_TTL', 5),
        'ssh_directory' => env('PODS_SSH_DIRECTORY', storage_path('app/private/ssh')),
        // Seconds an SSH connection to a host stays open after its last command, for the next
        // ones to reuse (0 opens one per command). Its socket lives outside the storage volume.
        'ssh_control_persist' => (int) env('PODS_SSH_CONTROL_PERSIST', 300),
        'ssh_control_directory' => env('PODS_SSH_CONTROL_DIRECTORY', sys_get_temp_dir().'/pods-ssh'),
        'local' => [
            'host' => env('PODS_LOCAL_SSH_HOST', 'host.containers.internal'),
            'port' => (int) env('PODS_LOCAL_SSH_PORT', 22),
            'user' => env('PODS_LOCAL_SSH_USER'),
        ],
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
