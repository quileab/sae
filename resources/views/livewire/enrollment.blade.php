<div>
    @if($blocked)
        <x-alert icon="o-exclamation-triangle" class="alert-error" title="Acceso Denegado">
            No tienes una carrera asignada. Por favor, contacta con administración para regularizar tu situación antes de matricularte.
        </x-alert>
    @else
        <h1 class="text-2xl font-bold">Materias »
            {{ session()->get('user_id_name') ? session()->get('user_id_name') : auth()->user()->fullname }}
        </h1>
        <x-select icon="o-academic-cap" :options="$careers" wire:model.lazy="careerId" />

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">

            @foreach($subjects as $subject)
                <div class="border border-white/10 rounded-lg overflow-hidden text-black dark:text-white">

                    <p class="p-2 border-b border-white/50 bg-blue-500/50 h-16 overflow-hidden">
                        <small>{{ $subject->id }}</small> <strong>{{ $subject->name }}</strong>
                    </p>

                    <div class="justify-end flex p-2 bg-gray-500/40">
                        <x-button label="{{ in_array($subject->id, $enrolledSubjects) ? 'Desmatricularse' : 'Matricularse' }}"
                            wire:click="save" wire:click="toggleEnrollment({{ $subject->id }})"
                            class="btn-sm {{ in_array($subject->id, $enrolledSubjects) ? 'bg-red-500/50 text-white' : 'bg-lime-500/50 text-white' }}" />
                    </div>

                </div>
            @endforeach

        </div>
    @endif
    <x-modal wire:model="modal" class="backdrop-blur" persistent>
        <div class="mb-5">Haga click para ser redirigido</div>
        <x-button label="CONTINUAR" link="/users" class="btn-primary" />
    </x-modal>
</div>