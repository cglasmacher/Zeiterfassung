<?php

return [
    'archive' => ['export_token' => env('CASH_DOCUMENT_EXPORT_TOKEN')],
    'z_reports' => [
        'import_token' => env('CASH_Z_REPORT_IMPORT_TOKEN'),
        'previous_day_until' => env('CASH_Z_REPORT_PREVIOUS_DAY_UNTIL', '11:00'),
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
