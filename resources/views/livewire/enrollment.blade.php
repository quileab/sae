<div>
    @if($blocked)
        <x-alert icon="o-exclamation-triangle" class="alert-error" title="Acceso Denegado">
            No tienes una carrera asignada. Por favor, contacta con administración para regularizar tu situación antes de matricularte.
        </x-alert>
    @else
        <h1 class="text-2xl font-bold">Materias »
            {{ $this->targetUser->fullname }}
        </h1>
        <x-select icon="o-academic-cap" :options="$this->careers" wire:model.live="careerId" />

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">

            @foreach($this->subjects as $subject)
                <div class="border border-white/10 rounded-lg overflow-hidden text-black dark:text-white">

                    <p class="p-2 border-b border-white/50 bg-blue-500/50 h-16 overflow-hidden">
                        <small>{{ $subject->id }}</small> <strong>{{ $subject->name }}</strong>
                    </p>

                    <div class="justify-end flex p-2 bg-gray-500/40">
                        <x-button label="{{ in_array($subject->id, $this->enrolledSubjectIds) ? 'Desmatricularse' : 'Matricularse' }}"
                            wire:click="toggleEnrollment({{ $subject->id }})"
                            class="btn-sm {{ in_array($subject->id, $this->enrolledSubjectIds) ? 'bg-red-500/50 text-white' : 'bg-lime-500/50 text-white' }}" />
                    </div>

                </div>
            @endforeach

        </div>
    @endif
    <x-modal wire:model="modal" class="backdrop-blur" persistent>
        <div class="mb-5 text-lg font-medium">{{ $modalMessage }}</div>
        <div class="flex gap-2">
            @if($user_id)
                <x-button label="IR AL PERFIL" link="/user/{{ $user_id }}" class="btn-primary" />
            @endif
            <x-button label="VOLVER A USUARIOS" link="/users" class="btn-ghost" />
        </div>
    </x-modal>
</div>