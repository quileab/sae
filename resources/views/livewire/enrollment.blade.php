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
                @php
                    $enrollment = $this->enrolledSubjects->get($subject->id);
                    $isEnrolled = $enrollment !== null;
                    $hasFinalGrade = $isEnrolled && $enrollment->final_grade !== null;
                    $headerColor = $hasFinalGrade ? 'bg-success/80 text-success-content' : ($isEnrolled ? 'bg-blue-500/50' : 'bg-gray-500/30');
                @endphp
                <div class="border border-white/10 rounded-lg overflow-hidden text-black dark:text-white flex flex-col justify-between">

                    <div class="p-3 border-b border-white/30 {{ $headerColor }} h-16 overflow-hidden flex justify-between items-start">
                        <span><small class="opacity-75">{{ $subject->id }}</small> <strong>{{ $subject->name }}</strong></span>
                    </div>

                    <div class="justify-between items-center flex p-2.5 bg-gray-500/40 min-h-[48px]">
                        @if($hasFinalGrade)
                            <span class="text-xs font-bold text-success flex items-center gap-1">
                                <x-icon name="o-check-circle" class="w-4 h-4 text-success" /> Aprobada
                            </span>
                            <div class="badge badge-success text-white font-bold px-3 py-1 text-sm shadow-sm">
                                Nota: {{ number_format((float)$enrollment->final_grade, 2) }}
                            </div>
                        @else
                            <div class="w-full flex justify-end">
                                <x-button label="{{ $isEnrolled ? 'Desmatricularse' : 'Matricularse' }}"
                                    wire:click="toggleEnrollment({{ $subject->id }})"
                                    class="btn-sm {{ $isEnrolled ? 'bg-red-500/50 text-white' : 'bg-lime-500/50 text-white' }}" />
                            </div>
                        @endif
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