<?php

declare(strict_types=1);

return [
    'incremental_sitemap_schedule' => [
        'enabled' => env('CAPELL_SITE_DISCOVERY_INCREMENTAL_SITEMAP_SCHEDULE', false),
        'frequency' => env('CAPELL_SITE_DISCOVERY_INCREMENTAL_SITEMAP_FREQUENCY', 'dailyAt'),
        'daily_at' => env('CAPELL_SITE_DISCOVERY_INCREMENTAL_SITEMAP_DAILY_AT', '02:30'),
        'cron' => env('CAPELL_SITE_DISCOVERY_INCREMENTAL_SITEMAP_CRON'),
        'overlap_expires_after_minutes' => (int) env('CAPELL_SITE_DISCOVERY_INCREMENTAL_SITEMAP_OVERLAP_MINUTES', 65),
    ],

    'indexnow' => [
        'enabled' => false,
        'endpoint' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'key' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY'),
        'key_location' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY_LOCATION'),
        'timeout' => 10,
    ],
];
