<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200 bg-cover bg-center"
    style="background-image: url('../background.jpg')">
    <x-main>
        <x-slot:content>
            <div class="mx-auto w-72 bg-slate-900 bg-opacity-40 backdrop-blur-xl rounded-lg shadow-sm shadow-black p-4">
            @if(isset($slot))
                {{ $slot }}
            @endif
            </div>
        </x-slot:content>
    </x-main>
</body>
</html>