<?php

return [
    'z_reports' => [
        'import_token' => env('CASH_Z_REPORT_IMPORT_TOKEN'),
        'business_day_starts_at' => env('CASH_BUSINESS_DAY_START_HOUR', 6),
    ],
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
