<?php

return [
    // The iframe and its API are hosted by this app, not by the WordPress page.
    'embed_origins' => array_filter(array_map('trim', explode(',', env('RESERVATION_WIDGET_ORIGINS', 'https://hemingway-hilden.de,https://www.hemingway-hilden.de')))),
];
