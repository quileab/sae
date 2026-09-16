<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ config('app.name') }} — Sistema de Gestión Académica">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>
    
    <!-- Mercado Pago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2" defer></script>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <main>
        {{ $slot }}
    </main>
    <x-toast />
    @stack('scripts')
</body>
</html>
