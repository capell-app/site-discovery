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

    'event_sitemap_regeneration' => [
        'debounce_seconds' => (int) env('CAPELL_SITE_DISCOVERY_EVENT_SITEMAP_DEBOUNCE_SECONDS', 60),
        'pending_grace_seconds' => (int) env('CAPELL_SITE_DISCOVERY_EVENT_SITEMAP_PENDING_GRACE_SECONDS', 30),
        'unique_for_seconds' => (int) env('CAPELL_SITE_DISCOVERY_EVENT_SITEMAP_UNIQUE_FOR_SECONDS', 900),
    ],

    'indexnow' => [
        'enabled' => false,
        'endpoint' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'key' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY'),
        'key_location' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY_LOCATION'),
        'timeout' => 10,
        // Outbound retry policy. IndexNow submissions are idempotent recrawl hints.
        'retry_times' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_RETRY_TIMES', 3),
        'retry_delay_ms' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_RETRY_DELAY_MS', 500),
        'retry_after_max_ms' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_RETRY_AFTER_MAX_MS', 60000),
    ],
];
