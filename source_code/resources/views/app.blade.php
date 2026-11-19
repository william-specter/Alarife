<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>


        @if (config('broadcasting.default') === 'reverb')
            <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
            <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.port', 8080) }}">
        @endif
        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
