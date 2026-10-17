<?php

return [
    'vectron' => [
        // Explicit production URL required; do not accidentally send credentials to demo.
        'base_url' => env('VECTRON_BI_BASE_URL'),
        'operator_id' => env('VECTRON_OPERATOR_ID'),
        'site_id' => env('VECTRON_SITE_ID'),
        'authorization_token' => env('VECTRON_API_KEY'),
        'api_token' => env('VECTRON_PARTNER_API_TOKEN'),
    ],
    'n8n' => [
        'url' => env('CASH_N8N_WEBHOOK_URL'),
        'token' => env('CASH_N8N_TOKEN'),
    ],
];
