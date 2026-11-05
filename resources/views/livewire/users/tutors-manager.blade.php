<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public int $userId;

    // Attach existing tutor/student state
    public bool $showAttachModal = false;

    public string $searchQuery = '';

    public ?int $selectedUserId = null;

    public string $relationship = 'Padre/Madre';

    public bool $isEmergencyContact = true;

    public bool $isAuthorizedPickup = true;

    public string $notes = '';

    // Quick create tutor state
    public bool $showCreateModal = false;

    public string $newDni = '';

    public string $newFirstname = '';

    public string $newLastname = '';

    public string $newEmail = '';

    public string $newPhone = '';

    public function user(): ?User
    {
        return User::with(['tutors', 'students'])->find($this->userId);
    }

    public function openAttachModal(): void
    {
        $this->reset(['searchQuery', 'selectedUserId', 'relationship', 'isEmergencyContact', 'isAuthorizedPickup', 'notes']);
        $this->relationship = 'Padre/Madre';
        $this->isEmergencyContact = true;
        $this->isAuthorizedPickup = true;
        $this->showAttachModal = true;
    }

    public function openCreateModal(): void
    {
        $this->reset(['newDni', 'newFirstname', 'newLastname', 'newEmail', 'newPhone', 'relationship', 'isEmergencyContact', 'isAuthorizedPickup', 'notes']);
        $this->relationship = 'Padre/Madre';
        $this->isEmergencyContact = true;
        $this->isAuthorizedPickup = true;
        $this->showCreateModal = true;
    }

    public function searchResults(): array
    {
        if (strlen(trim($this->searchQuery)) < 2) {
            return [];
        }

        $user = $this->user();
        if (! $user) {
            return [];
        }

        $isStudent = $user->role === UserRole::Student->value;
        $targetRole = $isStudent ? UserRole::Tutor->value : UserRole::Student->value;

        // Search users of the opposite role, excluding current user and already linked ones
        $existingLinkedIds = $isStudent
            ? $user->tutors->pluck('id')->all()
            : $user->students->pluck('id')->all();
        $existingLinkedIds[] = $user->id;

        return User::query()
            ->where('role', $targetRole)
            ->whereNotIn('id', $existingLinkedIds)
            ->where(function ($q) {
                $q->where('firstname', 'like', "%{$this->searchQuery}%")
                    ->orWhere('lastname', 'like', "%{$this->searchQuery}%")
                    ->orWhere('name', 'like', "%{$this->searchQuery}%")
                    ->orWhere('email', 'like', "%{$this->searchQuery}%");
            })
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => "{$u->lastname}, {$u->firstname} ({$u->name}) - {$u->email}",
            ])
            ->all();
    }

    public function attachUser(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'relationship' => 'required|string|max:50',
        ]);

        $user = $this->user();
        if (! $user) {
            return;
        }

        if ($user->role === UserRole::Student->value) {
            $user->tutors()->attach($this->selectedUserId, [
                'relationship' => $this->relationship,
                'is_emergency_contact' => $this->isEmergencyContact,
                'is_authorized_pickup' => $this->isAuthorizedPickup,
                'notes' => $this->notes,
            ]);
            $this->success('Tutor vinculado exitosamente.');
        } else {
            $user->students()->attach($this->selectedUserId, [
                'relationship' => $this->relationship,
                'is_emergency_contact' => $this->isEmergencyContact,
                'is_authorized_pickup' => $this->isAuthorizedPickup,
                'notes' => $this->notes,
            ]);
            $this->success('Estudiante vinculado exitosamente.');
        }

        $this->showAttachModal = false;
    }

    public function createAndAttachTutor(): void
    {
        $this->validate([
            'newDni' => 'required|string|max:20|unique:users,name',
            'newFirstname' => 'required|string|max:30',
            'newLastname' => 'required|string|max:30',
            'newEmail' => 'required|email|unique:users,email',
            'newPhone' => 'nullable|string|max:20',
            'relationship' => 'required|string|max:50',
        ]);

        $user = $this->user();
        if (! $user) {
            return;
        }

        $tutor = new User;
        $tutor->forceFill([
            'name' => $this->newDni,
            'firstname' => $this->newFirstname,
            'lastname' => $this->newLastname,
            'email' => $this->newEmail,
            'phone' => $this->newPhone,
            'role' => UserRole::Tutor->value,
            'enabled' => true,
            'status' => 'active',
        ]);
        $tutor->password = Hash::make(Str::random(12));
        $tutor->save();

        $user->tutors()->attach($tutor->id, [
            'relationship' => $this->relationship,
            'is_emergency_contact' => $this->isEmergencyContact,
            'is_authorized_pickup' => $this->isAuthorizedPickup,
            'notes' => $this->notes,
        ]);

        $this->showCreateModal = false;
        $this->success("Tutor {$tutor->fullname} creado y vinculado correctamente.");
    }

    public function detachUser(int $targetId): void
    {
        $user = $this->user();
        if (! $user) {
            return;
        }

        if ($user->role === UserRole::Student->value) {
            $user->tutors()->detach($targetId);
            $this->warning('Tutor desvinculado.');
        } else {
            $user->students()->detach($targetId);
            $this->warning('Estudiante desvinculado.');
        }
    }
}; ?>

<div>
    @php
        $currentUser = $this->user();
        $isStudent = $currentUser && $currentUser->role === App\Enums\UserRole::Student->value;
        $isTutor = $currentUser && $currentUser->role === App\Enums\UserRole::Tutor->value;
        $linkedUsers = $isStudent ? $currentUser->tutors : ($isTutor ? $currentUser->students : collect());
    @endphp

    @if($isStudent || $isTutor)
        <x-card :title="$isStudent ? 'Padres / Tutores Vinculados' : 'Estudiantes a Cargo'" shadow class="mt-4">
            <x-slot:menu>
                <div class="flex gap-2">
                    @if($isStudent)
                        <x-button label="Nuevo Tutor" icon="o-user-plus" class="btn-primary btn-sm" wire:click="openCreateModal" />
                    @endif
                    <x-button :label="$isStudent ? 'Vincular Tutor Existente' : 'Vincular Estudiante'" icon="o-link" class="btn-outline btn-sm" wire:click="openAttachModal" />
                </div>
            </x-slot:menu>

            @if($linkedUsers->isNotEmpty())
                <div class="overflow-x-auto border border-base-200 rounded-lg">
                    <table class="table table-sm w-full bg-base-100">
                        <thead class="bg-base-200/50">
                            <tr>
                                <th>Nombre / DNI</th>
                                <th>Parentesco</th>
                                <th>Contacto</th>
                                <th>Permisos</th>
                                <th class="w-16">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linkedUsers as $linked)
                                <tr class="hover:bg-base-200/40">
                                    <td>
                                        <div class="font-bold">{{ $linked->fullname }}</div>
                                        <div class="text-xs text-base-content/60">DNI: {{ $linked->name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-outline badge-sm">{{ $linked->pivot->relationship }}</span>
                                    </td>
                                    <td class="text-xs space-y-0.5">
                                        @if($linked->phone)
                                            <div class="flex items-center gap-1">
                                                <x-icon name="o-phone" class="w-3.5 h-3.5 text-primary" />
                                                <span>{{ $linked->phone }}</span>
                                            </div>
                                        @endif
                                        <div class="flex items-center gap-1">
                                            <x-icon name="o-envelope" class="w-3.5 h-3.5 text-base-content/60" />
                                            <span>{{ $linked->email }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @if($linked->pivot->is_emergency_contact)
                                                <span class="badge badge-warning badge-xs">Emergencia</span>
                                            @endif
                                            @if($linked->pivot->is_authorized_pickup)
                                                <span class="badge badge-success text-white badge-xs">Retiro autorizado</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <x-button 
                                            icon="o-trash" 
                                            class="btn-error btn-xs btn-square" 
                                            wire:click="detachUser({{ $linked->id }})" 
                                            wire:confirm="¿Seguro que deseas desvincular a este usuario?" 
                                            spinner="detachUser({{ $linked->id }})" 
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-6 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                    <x-icon name="o-users" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    <p class="text-xs">
                        {{ $isStudent ? 'No hay tutores vinculados a este estudiante.' : 'No hay estudiantes vinculados a este tutor.' }}
                    </p>
                </div>
            @endif
        </x-card>

        @teleport('body')
            {{-- MODAL VINCULAR EXISTENTE --}}
            <x-modal wire:model="showAttachModal" :title="$isStudent ? 'Vincular Tutor Existente' : 'Vincular Estudiante'" separator>
                <div class="space-y-4">
                    <x-input 
                        label="Buscar por DNI, Nombre o Email" 
                        placeholder="Escribe al menos 2 caracteres..." 
                        wire:model.live.debounce.300ms="searchQuery" 
                        icon="o-magnifying-glass" 
                        clear 
                    />

                    @php $results = $this->searchResults(); @endphp
                    @if(!empty($results))
                        <x-select 
                            label="Seleccionar usuario" 
                            :options="$results" 
                            wire:model="selectedUserId" 
                            option-value="id" 
                            option-label="name" 
                            placeholder="Elige una coincidencia" 
                        />
                    @elseif(strlen(trim($searchQuery)) >= 2)
                        <p class="text-xs text-warning">No se encontraron usuarios disponibles con ese criterio.</p>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <x-input label="Parentesco / Relación" wire:model="relationship" placeholder="Ej. Padre, Madre, Tutor Legal" />
                        <x-input label="Observaciones (opcional)" wire:model="notes" placeholder="Notas adicionales..." />
                    </div>

                    <div class="flex gap-4">
                        <x-checkbox label="Contacto de Emergencia" wire:model="isEmergencyContact" class="checkbox-primary" />
                        <x-checkbox label="Autorizado a Retirar" wire:model="isAuthorizedPickup" class="checkbox-success" />
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Cancelar" @click="$wire.showAttachModal = false" />
                    <x-button label="Vincular" icon="o-link" class="btn-primary" wire:click="attachUser" spinner="attachUser" />
                </x-slot:actions>
            </x-modal>

            {{-- MODAL CREAR NUEVO TUTOR RÁPIDAMENTE --}}
            <x-modal wire:model="showCreateModal" title="Crear y Vincular Nuevo Tutor" separator>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <x-input label="DNI" wire:model="newDni" placeholder="Número de documento" />
                        <x-input label="Nombres" wire:model="newFirstname" />
                        <x-input label="Apellido" wire:model="newLastname" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <x-input label="Email" type="email" wire:model="newEmail" placeholder="tutor@ejemplo.com" />
                        <x-input label="Teléfono" type="tel" wire:model="newPhone" placeholder="Ej. 11 1234 5678" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <x-input label="Parentesco" wire:model="relationship" placeholder="Ej. Padre, Madre" />
                        <x-input label="Notas" wire:model="notes" placeholder="Detalles de contacto..." />
                    </div>
                    <div class="flex gap-4 pt-2">
                        <x-checkbox label="Contacto de Emergencia" wire:model="isEmergencyContact" class="checkbox-primary" />
                        <x-checkbox label="Autorizado a Retirar" wire:model="isAuthorizedPickup" class="checkbox-success" />
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Cancelar" @click="$wire.showCreateModal = false" />
                    <x-button label="Guardar y Vincular" icon="o-check" class="btn-primary" wire:click="createAndAttachTutor" spinner="createAndAttachTutor" />
                </x-slot:actions>
            </x-modal>
        @endteleport
    @endif
</div>
