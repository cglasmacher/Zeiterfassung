<?php

return [
    'enabled' => (bool) env('RESERVATION_MAIL_ENABLED', false),
    'reply_to' => env('RESERVATION_MAIL_REPLY_TO'),
];
