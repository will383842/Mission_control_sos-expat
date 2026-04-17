<?php

use App\Services\Social\Drivers\FacebookDriver;
use App\Services\Social\Drivers\InstagramDriver;
use App\Services\Social\Drivers\LinkedInDriver;
use App\Services\Social\Drivers\ThreadsDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered social publishing drivers
    |--------------------------------------------------------------------------
    |
    | Each entry maps a platform slug → concrete driver class.
    | Set `enabled` to false to hide a platform from the dashboard and block
    | its publish cron (useful during Meta App Review waiting period).
    |
    | When adding a new platform:
    |   1. Create App\Services\Social\Drivers\{Name}Driver (extends AbstractSocialDriver)
    |   2. Register it here
    |   3. Add its credential block to config/services.php
    |   4. Add its platform slug to the CHECK constraints in the social_* migrations
    |
    */

    'drivers' => [

        'linkedin' => [
            'driver'   => LinkedInDriver::class,
            'enabled'  => true,
            'label'    => 'LinkedIn',
            'icon'     => 'linkedin',
            'queue'    => 'social_linkedin',
        ],

        'facebook' => [
            'driver'   => FacebookDriver::class,
            'enabled'  => (bool) env('SOCIAL_FACEBOOK_ENABLED', false),
            'label'    => 'Facebook',
            'icon'     => 'facebook',
            'queue'    => 'social_facebook',
        ],

        'threads' => [
            'driver'   => ThreadsDriver::class,
            'enabled'  => (bool) env('SOCIAL_THREADS_ENABLED', false),
            'label'    => 'Threads',
            'icon'     => 'threads',
            'queue'    => 'social_threads',
        ],

        'instagram' => [
            'driver'   => InstagramDriver::class,
            'enabled'  => (bool) env('SOCIAL_INSTAGRAM_ENABLED', false),
            'label'    => 'Instagram',
            'icon'     => 'instagram',
            'queue'    => 'social_instagram',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Editorial calendar defaults
    |--------------------------------------------------------------------------
    |
    | These values feed FillSocialCalendarCommand and the "next-slot" picker.
    | Overrideable per platform in the 'drivers.*.calendar' block if needed.
    |
    */

    'calendar' => [
        'default_days'     => ['monday', 'wednesday', 'friday', 'saturday'],
        'default_hour_utc' => 7,
        'saturday_hour_utc' => 9,
        'fill_ahead_days'  => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-publish cron
    |--------------------------------------------------------------------------
    */

    'auto_publish' => [
        'interval_minutes'   => 5,
        'first_comment_delay_seconds' => 180,
    ],

];
