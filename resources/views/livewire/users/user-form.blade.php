<div>
    <x-card shadow separator>
        <x-slot:title>
            <div class="flex items-center gap-3">
                <x-avatar :image="$this->avatarUrl" class="!w-10 !h-10" />
                <span>Usuario</span>
            </div>
        </x-slot:title>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive icon="o-ellipsis-vertical"
                class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
        <x-form wire:submit.prevent="save" no-separator>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <x-input label="ID" type="number" wire:model="data.id" />
                <div class="mt-8 mx-auto">
                    <x-toggle label="Habilitado" hint="Permite realizar inscripciones" wire:model="data.enabled"
                        class="toggle-success" />
                </div>
                <x-input label="ID Name" type="text" wire:model="data.name" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <x-input label="Apellido" type="text" wire:model="data.lastname" />
                <x-input label="Nombres" type="text" wire:model="data.firstname" />
                <x-input label="Fecha de Nacimiento" type="date" icon="o-calendar" wire:model="data.birth_date" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                <x-input label="E-mail" type="email" wire:model="data.email" />
                <x-input label="Teléfono" type="tel" wire:model="data.phone" />
                <x-select label="Rol" icon="o-user" :options="$this->roles" wire:model="data.role" option-value="name"
                    option-label="alias" />
                <x-select label="Estado" icon="o-flag" :options="$this->statuses" wire:model="data.status" option-value="name"
                    option-label="alias" />
            </div>

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
        {{ session('user_id') }}
    </x-card>

    @if(!empty($data['id']))
        <x-card title="Carreras asignadas" shadow class="mt-2">
            <div class="bg-white/10 dark:bg-black/10 p-4 rounded-md mb-2">
                @if(!empty($data['careers']) && count($data['careers']) > 0)
                    @foreach ($data['careers'] as $career)
                        @php
                            $careerId = is_array($career) ? ($career['id'] ?? null) : ($career->id ?? null);
                            $careerName = is_array($career) ? ($career['name'] ?? '') : ($career->name ?? '');
                        @endphp
                        <x-dropdown label="{{ $careerName }}" class="btn-primary">
                            {{-- para click wire:click.stop='action' --}}
                            <x-menu-item title="BORRAR" icon="o-trash" class="bg-error"
                                wire:click.stop="removeCareer({{ $careerId }})" />
                        </x-dropdown>
                    @endforeach
                @else
                    <p class="text-xs text-base-content/60 italic">Sin carreras asignadas.</p>
                @endif
            </div>

            <x-form wire:submit.prevent="assignCareer" no-separator>
                <x-select label="Carrera Disponibles" icon="o-academic-cap" :options="$this->careers" wire:model.lazy="career_id">
                    <x-slot:append>
                        <x-button label="Asignar" icon="o-plus" class="rounded-s-none btn-primary" type="submit"
                            spinner="assignCareer" />
                    </x-slot:append>
                </x-select>
            </x-form>
        </x-card>
    @endif

    @if(!empty($data['id']) && in_array($data['role'] ?? '', ['student', 'tutor']))
        @livewire('users.tutors-manager', ['userId' => $data['id']], key('tutors-'.$data['id']))
    @endif

    @if(!empty($data['id']) && ($data['role'] ?? '') === 'student')
        @livewire('users.medical-record-manager', ['userId' => $data['id']], key('medical-'.$data['id']))
    @endif

    @if(!empty($data['id']) && in_array($data['role'] ?? '', ['student', 'teacher']))
        <x-card title="Justificaciones (Certificados / Licencias)" shadow class="mt-4">
            {{-- Formulario para agregar una nueva justificación --}}
            <x-form wire:submit.prevent="addJustifiedAbsence" no-separator class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <x-input label="Desde" type="date" wire:model="justificationStartDate" />
                    <x-input label="Hasta" type="date" wire:model="justificationEndDate" />
                    <x-input label="Descripción / Motivo" type="text" placeholder="Ej. Certificado Médico por Gripe" wire:model="justificationDescription" />
                </div>
                <x-slot:actions>
                    <x-button label="Agregar Justificación" icon="o-plus" class="btn-primary btn-sm" type="submit" spinner="addJustifiedAbsence" />
                </x-slot:actions>
            </x-form>

            {{-- Listado de justificaciones --}}
            @if(count($justifiedAbsences) > 0)
                <div class="overflow-x-auto border border-base-200 rounded-lg">
                    <table class="table table-xs w-full bg-white/10 dark:bg-black/10">
                        <thead>
                            <tr>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Artículo / Motivo</th>
                                <th>Estado</th>
                                <th class="w-16">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($justifiedAbsences as $ja)
                                <tr class="hover:bg-base-200/50">
                                    <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($ja['start_date'])->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($ja['end_date'])->format('d/m/Y') }}</td>
                                    <td>
                                        @if(!empty($ja['article']))
                                            <span class="font-semibold text-primary">[{{ $ja['article'] }}]</span>
                                        @endif
                                        {{ $ja['description'] ?: ($ja['custom_article'] ?? '-') }}
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($ja['status'] ?? 'approved') {
                                                'pending' => 'badge-warning',
                                                'approved' => 'badge-success text-white',
                                                'rejected' => 'badge-error text-white',
                                                default => 'badge-ghost',
                                            };
                                            $statusText = match($ja['status'] ?? 'approved') {
                                                'pending' => 'Pendiente',
                                                'approved' => 'Aprobada',
                                                'rejected' => 'Rechazada',
                                                default => $ja['status'] ?? 'Aprobada',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} badge-xs">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td>
                                        <x-button icon="o-trash" class="btn-error btn-xs btn-square" wire:click="deleteJustifiedAbsence({{ $ja['id'] }})" spinner="deleteJustifiedAbsence({{ $ja['id'] }})" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-6 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                    <x-icon name="o-document-text" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    <p class="text-xs">No hay justificaciones registradas para este usuario.</p>
                </div>
            @endif
        </x-card>
    @endif

    <!-- DRAWER -->
    <x-qb-drawer wire:model="drawer" title="Acciones" right with-close-button separator with-close-button close-on-escape
        class="lg:w-1/3">
        <x-input inline label="Password" wire:model="data.password" type="text" icon="o-key" error-field="data.password">
            <x-slot:append>
                <x-button label="Cambiar Clave" icon="o-check" class="btn-primary rounded-s-none"
                    wire:click="changePassword" spinner="changePassword" />
            </x-slot:append>
        </x-input>
        <x-slot:actions>
            <div class="w-full p-4 border border-error/40 rounded-lg bg-error/10 space-y-3">
                <div class="flex items-start gap-2 text-error">
                    <x-icon name="o-exclamation-triangle" class="w-5 h-5 mt-0.5 shrink-0" />
                    <div>
                        <div class="font-bold text-sm">Zona de peligro</div>
                        <p class="text-xs text-error/80 mt-1">
                            Eliminar este usuario borrará <strong>permanentemente</strong> todos sus datos asociados:
                            inscripciones, pagos, notas y asistencias. Esta acción <strong>no se puede deshacer</strong>.
                        </p>
                    </div>
                </div>
                <x-dropdown label="ELIMINAR USUARIO" class="btn-error w-full">
                    <x-menu-item title="Confirmar eliminación" wire:click.stop="delete" spinner="delete" icon="o-trash"
                        class="bg-error text-white" />
                </x-dropdown>
            </div>
        </x-slot:actions>
    </x-qb-drawer>
</div>