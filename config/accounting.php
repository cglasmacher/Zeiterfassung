<?php

return [
    'import_token' => env('ACCOUNTING_IMPORT_TOKEN'),

    'mailbox' => [
        'host' => env('ACCOUNTING_IMAP_HOST', 'imap.ionos.de'),
        'port' => (int) env('ACCOUNTING_IMAP_PORT', 993),
        'encryption' => env('ACCOUNTING_IMAP_ENCRYPTION', 'ssl'),
        'mailbox' => env('ACCOUNTING_IMAP_MAILBOX', 'INBOX'),
        'username' => env('ACCOUNTING_IMAP_USERNAME'),
        'password' => env('ACCOUNTING_IMAP_PASSWORD'),
        'max_messages' => (int) env('ACCOUNTING_IMAP_MAX_MESSAGES', 10),
        'max_attachment_bytes' => (int) env('ACCOUNTING_IMAP_MAX_ATTACHMENT_BYTES', 15728640),
    ],
];
