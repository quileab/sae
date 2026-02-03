<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">
  <img id="background" class="fixed left-0 top-0 w-full h-auto" src="../background.jpg" />
  <x-main full-width="true">
    <x-slot:content>
      <div class="mx-auto bg-slate-800 bg-opacity-30 backdrop-blur-sm rounded-lg shadow-sm shadow-black p-4">
        <div class="my-4 text-center grid grid-cols-2 gap-2 md:grid-cols-4">
          {{-- logo --}}
          <img src="../logo.webp" class="w-20 h-auto mx-auto col-start-1 col-span-1">
          <div class="text-center text-white w-full col-start-2 col-span-3">
            <h2 class="text-xl font-bold">Bienvenido</h2>
            <p class="text-sm text-justify mb-1">
            Para poder procesar el registro de manera correcta, te pedimos que completes el formulario con atención y asegurándote de que todos los datos sean precisos.
            </p>
            <hr>
            <p class="text-xs text-justify my-1">
            ⚠️ IMPORTANTE<br>
            &nbsp;&nbsp;✔️ Los datos incorrectos pueden causar demoras o errores en el procesamiento de tu solicitud.<br>
            &nbsp;&nbsp;✔️ Asegúrese que la información proporcionada sea precisa y veraz.
            </p>
          </div>
        </div>
        {{ $slot }}
      </div>
    </x-slot:content>
  </x-main>
  <x-toast />
</body>

</html>
