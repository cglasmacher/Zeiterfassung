<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Tisch reservieren · Hemingway Hilden</title>
    @viteReactRefresh
    @vite('resources/js/reservation-widget.jsx')
</head>
<body>
    <div id="reservation-widget" data-configuration="{{ json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"></div>
</body>
</html>
