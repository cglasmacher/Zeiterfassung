<?php

return [
    'stream_host' => env('INFO_STREAM_HOST', 'stream.hemingway-hilden.de'),
    'timezone' => 'Europe/Berlin',
    'youtube_api_key' => env('YOUTUBE_API_KEY', ''),
    // https://documentation.resmio.com/authenticatedapi.html (authenticated v1 API).
    'resmio_reconcile_missing' => env('RESMIO_RECONCILE_MISSING', false),
    'resmio_base_url' => env('RESMIO_BASE_URL', 'https://api.resmio.com/v1'),
];
