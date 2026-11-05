<x-layouts.public>
    <div class="mx-auto max-w-3xl p-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h1 class="card-title text-2xl">Materiales para inscriptos</h1>
                <p class="text-sm opacity-70">
                    {{ $preEnrollment->full_name }}
                    @if ($preEnrollment->career)
                        — {{ $preEnrollment->career->name }}
                    @endif
                </p>

                <div class="divider"></div>

                @forelse ($materials as $material)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <span>{{ $material->title }}</span>
                        <a class="btn btn-primary btn-sm"
                           href="{{ URL::temporarySignedRoute('preinsc.materials.download', now()->addDays(2), ['preEnrollment' => $preEnrollment->getKey(), 'material' => $material->getKey()]) }}">
                            Descargar
                        </a>
                    </div>
                @empty
                    <p class="opacity-70">
                        Todavía no hay materiales disponibles. Volvé a consultar el estado de tu inscripción en unos días.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.public>
