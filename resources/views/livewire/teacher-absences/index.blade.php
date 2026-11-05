<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\JustifiedAbsence;
use App\Models\User;
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $teacherId = null;

    // Drawer de edición o detalle
    public bool $drawer = false;
    public ?JustifiedAbsence $selectedAbsence = null;
    public string $editStatus = '';
    public string $editDescription = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function markAsReceived(int $id): void
    {
        $absence = JustifiedAbsence::findOrFail($id);
        $absence->update([
            'status' => 'approved',
            'certificate_received_at' => now(),
        ]);

        $this->success('Documentación marcada como recibida y aprobada.');
    }

    public function markAsRejected(int $id): void
    {
        $absence = JustifiedAbsence::findOrFail($id);
        $absence->update([
            'status' => 'rejected',
        ]);

        $this->warning('Aviso de ausencia marcado como rechazado.');
    }

    public function deleteAbsence(int $id): void
    {
        $absence = JustifiedAbsence::findOrFail($id);
        $absence->delete();
        $this->success('Registro eliminado.');
    }

    public function openEdit(int $id): void
    {
        $this->selectedAbsence = JustifiedAbsence::with('user')->findOrFail($id);
        $this->editStatus = $this->selectedAbsence->status ?? 'pending';
        $this->editDescription = $this->selectedAbsence->description ?? '';
        $this->drawer = true;
    }

    public function saveEdit(): void
    {
        if ($this->selectedAbsence) {
            $updateData = [
                'status' => $this->editStatus,
                'description' => $this->editDescription,
            ];

            if ($this->editStatus === 'approved' && ! $this->selectedAbsence->certificate_received_at) {
                $updateData['certificate_received_at'] = now();
            }

            $this->selectedAbsence->update($updateData);
            $this->drawer = false;
            $this->success('Aviso actualizado correctamente.');
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo', 'teacherId']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = JustifiedAbsence::query()
            ->with('user')
            ->whereHas('user', function ($q) {
                $q->whereIn('role', ['teacher', 'admin', 'principal', 'director', 'administrative', 'preceptor']);
            });

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('firstname', 'like', "%{$this->search}%")
                  ->orWhere('lastname', 'like', "%{$this->search}%")
                  ->orWhere('id', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('end_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('start_date', '<=', $this->dateTo);
        }

        $absences = $query->latest('start_date')->paginate(20);

        $statusOptions = [
            ['id' => 'pending', 'name' => 'Pendientes'],
            ['id' => 'approved', 'name' => 'Aprobadas'],
            ['id' => 'rejected', 'name' => 'Rechazadas'],
        ];

        return [
            'absences' => $absences,
            'statusOptions' => $statusOptions,
        ];
    }
}; ?>

<div class="p-4 sm:p-6 space-y-6">
    <x-header title="Gestión de Licencias y Ausencias Docentes" subtitle="Control de recepción de documentación física (72 hs) y aprobación de licencias" separator>
        <x-slot:actions>
            <x-button label="Limpiar Filtros" icon="o-x-mark" class="btn-ghost btn-sm" wire:click="clearFilters" />
        </x-slot:actions>
    </x-header>

    {{-- Filtros --}}
    <x-card shadow class="bg-base-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <x-input 
                label="Buscar Docente" 
                placeholder="Nombre, Apellido, DNI..." 
                icon="o-magnifying-glass" 
                wire:model.live.debounce.300ms="search" 
            />
            <x-select 
                label="Estado" 
                :options="$statusOptions" 
                wire:model.live="statusFilter" 
                placeholder="Todos los estados" 
                icon="o-funnel" 
            />
            <x-input 
                label="Desde" 
                type="date" 
                wire:model.live="dateFrom" 
                icon="o-calendar" 
            />
            <x-input 
                label="Hasta" 
                type="date" 
                wire:model.live="dateTo" 
                icon="o-calendar" 
            />
        </div>
    </x-card>

    {{-- Tabla de Ausencias --}}
    <x-card shadow>
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-xs uppercase bg-base-200/50">
                        <th>Docente</th>
                        <th>Período</th>
                        <th>Artículo Solicitado</th>
                        <th>Observaciones</th>
                        <th>Estado / Doc. 72hs</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($absences as $abs)
                        <tr class="hover:bg-base-200/40">
                            <td>
                                <div class="font-bold text-sm">{{ $abs->user?->fullname ?? 'Docente no encontrado' }}</div>
                                <div class="text-xs text-base-content/70">DNI: {{ $abs->user_id }}</div>
                            </td>
                            <td class="whitespace-nowrap text-xs">
                                <div class="font-semibold">{{ $abs->start_date->format('d/m/Y') }}</div>
                                <div class="text-base-content/60 text-[11px]">al {{ $abs->end_date->format('d/m/Y') }}</div>
                                <div class="text-[10px] text-base-content/50">
                                    ({{ $abs->start_date->diffInDays($abs->end_date) + 1 }} {{ Str::plural('día', $abs->start_date->diffInDays($abs->end_date) + 1) }})
                                </div>
                            </td>
                            <td class="text-xs">
                                <div class="font-medium text-primary">{{ $abs->article_label }}</div>
                            </td>
                            <td class="text-xs max-w-xs truncate" title="{{ $abs->description }}">
                                {{ $abs->description ?: '-' }}
                            </td>
                            <td>
                                <span class="badge {{ $abs->status_badge }} badge-xs sm:badge-sm">
                                    {{ $abs->status_label }}
                                </span>
                                @if($abs->certificate_received_at)
                                    <div class="text-[10px] text-success mt-0.5 font-medium">
                                        Recibido: {{ $abs->certificate_received_at->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                @if($abs->status === 'pending')
                                    <x-button 
                                        icon="o-check" 
                                        label="Doc. Recibida"
                                        class="btn-success btn-xs text-white" 
                                        wire:click="markAsReceived({{ $abs->id }})" 
                                        spinner="markAsReceived({{ $abs->id }})" 
                                    />
                                    <x-button 
                                        icon="o-x-mark" 
                                        class="btn-ghost btn-xs text-error" 
                                        wire:click="markAsRejected({{ $abs->id }})" 
                                        wire:confirm="¿Desea marcar como rechazada esta licencia?"
                                        spinner="markAsRejected({{ $abs->id }})" 
                                    />
                                @endif
                                <x-button 
                                    icon="o-pencil-square" 
                                    class="btn-ghost btn-xs text-info" 
                                    wire:click="openEdit({{ $abs->id }})" 
                                />
                                <x-button 
                                    icon="o-trash" 
                                    class="btn-ghost btn-xs text-error" 
                                    wire:click="deleteAbsence({{ $abs->id }})" 
                                    wire:confirm="¿Seguro que deseas eliminar este registro permanentemente?" 
                                    spinner="deleteAbsence({{ $abs->id }})" 
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-base-content/50">
                                <x-icon name="o-document-text" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                No se encontraron registros de licencias docentes con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $absences->links() }}
        </div>
    </x-card>

    {{-- Drawer personalizado para Detalle / Edición --}}
    <x-qb-drawer wire:model="drawer" title="Detalle / Edición de Licencia" right separator with-close-button close-on-escape class="lg:w-1/3">
        @if($selectedAbsence)
            <div class="space-y-4">
                <div class="bg-base-200/60 p-4 rounded-xl text-xs space-y-2 border border-base-300">
                    <div><span class="font-bold text-base-content/70">Docente:</span> <span class="font-semibold text-sm">{{ $selectedAbsence->user?->fullname }}</span></div>
                    <div><span class="font-bold text-base-content/70">DNI:</span> {{ $selectedAbsence->user_id }}</div>
                    <div><span class="font-bold text-base-content/70">Email:</span> {{ $selectedAbsence->user?->email }}</div>
                    <div><span class="font-bold text-base-content/70">Artículo:</span> <span class="text-primary font-medium">{{ $selectedAbsence->article_label }}</span></div>
                    <div><span class="font-bold text-base-content/70">Período:</span> {{ $selectedAbsence->start_date->format('d/m/Y') }} al {{ $selectedAbsence->end_date->format('d/m/Y') }}</div>
                    @if($selectedAbsence->certificate_received_at)
                        <div class="text-success"><span class="font-bold">Doc. en papel recibida:</span> {{ $selectedAbsence->certificate_received_at->format('d/m/Y H:i') }}</div>
                    @endif
                </div>

                <x-select 
                    label="Estado de la Licencia" 
                    :options="$statusOptions" 
                    wire:model="editStatus" 
                />

                <x-textarea 
                    label="Observaciones" 
                    wire:model="editDescription" 
                    rows="4" 
                    placeholder="Detalles u observaciones administrativas..."
                />
            </div>
        @endif

        <x-slot:actions>
            <div class="flex justify-between items-center w-full">
                <x-button label="Cerrar" @click="$wire.drawer = false" class="btn-ghost" />
                <x-button label="Guardar Cambios" class="btn-primary" wire:click="saveEdit" spinner="saveEdit" icon="o-check" />
            </div>
        </x-slot:actions>
    </x-qb-drawer>
</div>
