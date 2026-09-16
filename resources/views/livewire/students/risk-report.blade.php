<div>
    <x-header title="Estudiantes en Riesgo" subtitle="Ciclo {{ $cycle }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex flex-wrap gap-2">
                <x-select wire:model.live="cycle" :options="[['id' => 2024, 'name' => '2024'], ['id' => 2025, 'name' => '2025'], ['id' => 2026, 'name' => '2026']]" 
                    placeholder="Ciclo" icon="o-calendar" class="w-32" />
                
                <x-select wire:model.live="career_id" :options="$this->careers" placeholder="TODAS LAS CARRERAS" icon="o-academic-cap" />
                
                @if($career_id)
                    <x-select wire:model.live="subject_id" :options="$this->subjects" placeholder="TODAS LAS MATERIAS" icon="o-queue-list" />
                @endif
            </div>
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 gap-6">
        @php
            $riskData = $this->riskStudents;
            $criticalCount = $riskData->where('risk_level', 'Crítico')->count();
            $warningCount = $riskData->where('risk_level', 'Atención')->count();
        @endphp

        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat title="Total en Riesgo" value="{{ $riskData->count() }}" icon="o-exclamation-triangle" class="bg-base-100 shadow-sm" />
            <x-stat title="Nivel Crítico" value="{{ $criticalCount }}" icon="o-no-symbol" class="text-error bg-error/5 border-t-4 border-t-error shadow-sm" />
            <x-stat title="Nivel Atención" value="{{ $warningCount }}" icon="o-bell-alert" class="text-warning bg-warning/5 border-t-4 border-t-warning shadow-sm" />
        </div>

        <x-card shadow-sm separator>
            @if($riskData->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-success">
                    <x-icon name="o-check-circle" class="w-16 h-16 mb-4" />
                    <h3 class="text-xl font-bold">¡Sin estudiantes en riesgo!</h3>
                    <p class="opacity-70">No se detectaron riesgos según los parámetros seleccionados.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                @if(!$subject_id)
                                    <th>Materia / Carrera</th>
                                @endif
                                <th class="text-center">Asistencia</th>
                                <th class="text-center">Notas (EV/TP)</th>
                                <th>Motivos de Alerta</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($riskData as $student)
                                <tr @class([
                                    "hover:bg-base-200/50",
                                    "bg-error/5" => $student['risk_level'] === 'Crítico'
                                ])>
                                    <td>
                                        <div class="font-bold">{{ $student['fullname'] }}</div>
                                        <div class="text-xs opacity-50">DNI: {{ $student['id'] }}</div>
                                    </td>
                                    @if(!$subject_id)
                                        <td class="max-w-xs">
                                            <div class="truncate font-medium">{{ $student['subject'] }}</div>
                                            <div class="text-xs opacity-60 truncate">{{ $student['career'] }}</div>
                                        </td>
                                    @endif
                                    <td class="text-center">
                                        <div @class([
                                            "radial-progress text-[10px] font-bold",
                                            "text-error" => $student['attendance'] < 50,
                                            "text-warning" => $student['attendance'] >= 50 && $student['attendance'] < 75,
                                            "text-success" => $student['attendance'] >= 75
                                        ]) style="--value:{{ $student['attendance'] }}; --size:2.5rem; --thickness: 3px;">
                                            {{ $student['attendance'] }}%
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex flex-col gap-1 items-center">
                                            <x-badge :value="'EV: '.$student['avg_ev']" @class([
                                                "badge-sm font-bold",
                                                "badge-error" => $student['avg_ev'] < 6 && $student['avg_ev'] > 0,
                                                "badge-ghost" => $student['avg_ev'] == 0
                                            ]) />
                                            <x-badge :value="'TP: '.$student['avg_tp']" @class([
                                                "badge-sm font-bold",
                                                "badge-error" => $student['avg_tp'] < 6 && $student['avg_tp'] > 0,
                                                "badge-ghost" => $student['avg_tp'] == 0
                                            ]) />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($student['reasons'] as $reason)
                                                <x-badge :value="$reason" @class([
                                                    "badge-xs py-2",
                                                    "badge-error" => str_contains($reason, 'Asist') && $student['attendance'] < 50,
                                                    "badge-ghost" => !str_contains($reason, 'Asist')
                                                ]) />
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="join">
                                            <x-button icon="o-chat-bubble-left" class="btn-sm join-item btn-ghost text-primary" link="/chat?user_id={{ $student['id'] }}" tooltip="Contactar" />
                                            <x-button icon="o-user" class="btn-sm join-item btn-ghost text-info" wire:click="viewProfile({{ $student['id'] }})" tooltip="Ver Perfil" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <!-- PROFILE MODAL -->
    <x-modal wire:model="profileModal" class="backdrop-blur" title="Perfil del Estudiante">
        @if($studentProfile)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-4 border-b border-base-300 pb-4">
                    <x-avatar :image="$studentProfile->avatar_url" class="!w-16 !rounded-lg bg-primary text-white text-2xl" />
                    <div>
                        <h2 class="text-2xl font-bold">{{ $studentProfile->lastname }}, {{ $studentProfile->firstname }}</h2>
                        <p class="text-sm opacity-70">DNI: {{ $studentProfile->id }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-list-item :item="$studentProfile" no-hover no-separator>
                        <x-slot:avatar>
                            <x-icon name="o-envelope" class="text-primary" />
                        </x-slot:avatar>
                        <x-slot:value>Email</x-slot:value>
                        <x-slot:sub-value>{{ $studentProfile->email }}</x-slot:sub-value>
                        <x-slot:actions>
                            <x-button icon="o-clipboard" class="btn-ghost btn-xs" @click="navigator.clipboard.writeText('{{ $studentProfile->email }}'); $wire.success('Email copiado')" />
                        </x-slot:actions>
                    </x-list-item>

                    <x-list-item :item="$studentProfile" no-hover no-separator>
                        <x-slot:avatar>
                            <x-icon name="o-phone" class="text-success" />
                        </x-slot:avatar>
                        <x-slot:value>Teléfono</x-slot:value>
                        <x-slot:sub-value>{{ $studentProfile->phone ?: 'No registrado' }}</x-slot:sub-value>
                        <x-slot:actions>
                            @if($studentProfile->phone)
                                <x-button icon="o-clipboard" class="btn-ghost btn-xs" @click="navigator.clipboard.writeText('{{ $studentProfile->phone }}'); $wire.success('Teléfono copiado')" />
                            @endif
                        </x-slot:actions>
                    </x-list-item>
                </div>

                <div class="bg-base-200 rounded-lg p-4">
                    <h3 class="font-bold mb-2 flex items-center gap-2">
                        <x-icon name="o-academic-cap" class="w-4 h-4" />
                        Carreras Inscriptas
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($studentProfile->careers as $career)
                            <x-badge :value="$career->name" class="badge-outline" />
                        @empty
                            <p class="text-sm opacity-50 italic">Sin carreras asignadas</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button label="Marcar" icon="o-bookmark" wire:click="$dispatch('bookmarked', {type: 'user_id', value: {{ $studentProfile?->id }}})" class="btn-outline btn-sm" />
            <x-button label="Ir al Chat" icon="o-chat-bubble-left" link="/chat?user_id={{ $studentProfile?->id }}" class="btn-primary btn-sm" />
            <x-button label="Cerrar" @click="$wire.profileModal = false" class="btn-sm" />
        </x-slot:actions>
    </x-modal>
</div>
