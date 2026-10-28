<?php

return [
    'lexware_clearing_account' => env('ACCOUNTING_LEXWARE_CLEARING_ACCOUNT', '1370'),
    'import_token' => env('ACCOUNTING_IMPORT_TOKEN'),
    'pdftotext_binary' => env('ACCOUNTING_PDFTOTEXT_BINARY', '/usr/bin/pdftotext'),
    'pdfimages_binary' => env('ACCOUNTING_PDFIMAGES_BINARY', '/usr/bin/pdfimages'),
    'pdfdetach_binary' => env('ACCOUNTING_PDFDETACH_BINARY', '/usr/bin/pdfdetach'),
    'gato_letterhead_hashes' => [
        '94a12b4ed66bc0ea418b47efb4da9abfe652e4cc40f34c64b46d0c174b4a68a6',
    ],

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
