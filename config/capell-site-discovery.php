<?php

declare(strict_types=1);

return [
    'indexnow' => [
        'enabled' => false,
        'endpoint' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'key' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY'),
        'key_location' => env('CAPELL_SITE_DISCOVERY_INDEXNOW_KEY_LOCATION'),
        'timeout' => 10,
    ],
];
