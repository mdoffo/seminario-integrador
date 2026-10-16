<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fuentes del sistema visual Atalaya: Inter (UI) + JetBrains Mono (telemetría) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,900|jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        {{-- Migración gradual a TypeScript: la página puede ser .tsx o .jsx. --}}
        @php($pagina = "resources/js/Pages/{$page['component']}")
        @vite(['resources/js/app.jsx', file_exists(base_path("{$pagina}.tsx")) ? "{$pagina}.tsx" : "{$pagina}.jsx"])
        @inertiaHead
    </head>
    <body class="bg-atalaya-canvas font-sans text-atalaya-text-primary antialiased">
        @inertia
    </body>
</html>
