<?php

return [
    'stream_host' => env('INFO_STREAM_HOST', 'stream.hemingway-hilden.de'),
    'timezone' => 'Europe/Berlin',
    // Confirm this endpoint with the API access supplied by resmio.
    'resmio_reconcile_missing' => env('RESMIO_RECONCILE_MISSING', false),
    'resmio_base_url' => env('RESMIO_BASE_URL', 'https://api.resmio.com/v1'),
];
