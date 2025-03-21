<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200 bg-cover bg-center"
    style="background-image: url({{ asset('storage/background.jpg') }})">
    <x-main>
        <x-slot:content>
            @if(isset($slot))
                {{ $slot }}
            @endif
        </x-slot:content>
    </x-main>
</body>
</html>