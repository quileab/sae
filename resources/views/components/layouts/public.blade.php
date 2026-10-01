<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preinscripción {{ \App\Services\AcademicCycle::enrollmentCycle() }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="bg-base-200 min-h-screen"><div class="content-list">{{ $slot }}<x-toast /></div></body>
</html>
