<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>Hemingway</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=20261018">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}?v=20261018">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}?v=20261018">
        <meta name="theme-color" content="#8cae76">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
