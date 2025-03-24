<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">
    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="p-5 pt-3" />

            {{-- MENU --}}
            <x-menu activate-by-route>

                {{-- SETEO User --}}
                @if($user = auth()->user())
                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover
                        class="-mx-2 !-mt-2 rounded bg-black/20">
                        <x-slot:actions>
                            <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff"
                                no-wire-navigate link="/logout" />
                        </x-slot:actions>
                    </x-list-item>
                    <livewire:bookmarks />
                @endif

                <x-menu-item title="Dashboard" icon="o-sparkles" link="/dashboard" />
                <x-menu-item title="Usuarios" icon="o-users" link="/users" />
                <x-menu-item title="Comunicación" icon="o-chat-bubble-left-right" link="/users" />
                <x-menu-sub title="{{ config('app.name') }}" icon="o-building-library">
                    <x-menu-item title="Carreras" icon="o-academic-cap" link="/careers" />
                    <x-menu-item title="Materias" icon="o-rectangle-stack" link="/subjects" />
                    <x-menu-item title="Materias-Usuarios" icon="o-arrow-path-rounded-square" link="/enrollments" />
                </x-menu-sub>
                <x-menu-sub title="Clases" icon="o-document-duplicate">
                    <x-menu-item title="Libros de Temas" icon="o-book-open" link="/class-sessions" />
                    <x-menu-item title="Mis Estudiantes" icon="o-user-group" link="/class-sessions/students" />
                </x-menu-sub>
                <x-menu-item title="Inscripciones" icon="o-clipboard-document-check" link="/inscriptions" />
                @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                    <x-menu-sub title="Admin Inscripciones" icon="o-clipboard-document-check">
                @endif
                    @if($user->hasAnyRole(['admin', 'principal', 'administrative', 'teacher']))
                        <x-menu-item title="Inscriptos" icon="o-clipboard-document-list" link="/inscriptions/list" />
                    @endif
                    @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                            <x-menu-item title="Inscriciones PDFs" icon="o-clipboard-document" link="/inscriptions/pdfs" />
                        </x-menu-sub>
                        <x-menu-sub title="Configuración" icon="o-cog-6-tooth">
                            <x-menu-item title="Parámetros" icon="o-adjustments-horizontal" link="/configs" />
                            <x-menu-item title="Caché" icon="o-wrench-screwdriver" link="/clear" />
                        </x-menu-sub>
                    @endif
            </x-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{-- TOAST area --}}
    <x-toast />
</body>

</html>