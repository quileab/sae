<?php

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    public string $search = '';
    public string $filterPriority = '';
    public bool $onlyPendingAck = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyPendingAck(): void
    {
        $this->resetPage();
    }

    public function acknowledge(int $id): void
    {
        $announcement = Event::announcements()->find($id);
        if ($announcement) {
            $announcement->acknowledgeFor(Auth::user());
            $this->success('Has confirmado la recepción y lectura de esta circular.');
        }
    }

    public function markAsRead(int $id): void
    {
        $announcement = Event::announcements()->find($id);
        if ($announcement) {
            $announcement->markAsReadFor(Auth::user());
        }
    }

    public function announcements(): LengthAwarePaginator
    {
        $user = Auth::user();

        return Event::query()
            ->announcements()
            ->published()
            ->forUser($user)
            ->with(['user', 'career', 'subject'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->onlyPendingAck, function ($q) use ($user) {
                $q->where('requires_acknowledgment', true)
                    ->whereDoesntHave('readers', function ($r) use ($user) {
                        $r->where('user_id', $user->id)
                            ->whereNotNull('acknowledged_at');
                    });
            })
            ->latest('published_at')
            ->paginate(10);
    }

    public function with(): array
    {
        $announcements = $this->announcements();

        // Auto mark as read on render for the logged in user
        foreach ($announcements as $ann) {
            $ann->markAsReadFor(Auth::user());
        }

        return [
            'announcementList' => $announcements,
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Circulares y Novedades" subtitle="Comunicaciones institucionales, avisos y notificaciones oficiales" separator progress-indicator>
        <x-slot:middle class="!justify-end flex items-center gap-3">
            <x-input 
                placeholder="Buscar por palabra clave..." 
                wire:model.live.debounce.300ms="search" 
                icon="o-magnifying-glass" 
                clear 
                class="input-sm" 
            />
        </x-slot:middle>

        <x-slot:actions>
            @if(auth()->user()->isStaff())
                <x-button 
                    label="Gestionar y Redactar" 
                    icon="o-pencil-square" 
                    class="btn-primary btn-sm" 
                    link="/announcements/manage" 
                />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- FILTROS RÁPIDOS --}}
    <div class="flex flex-wrap items-center justify-between gap-4 bg-base-100 p-3 rounded-xl border border-base-200">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-base-content/60">Prioridad:</span>
            <button 
                type="button" 
                wire:click="$set('filterPriority', '')" 
                class="btn btn-xs {{ $filterPriority === '' ? 'btn-neutral' : 'btn-ghost' }}"
            >
                Todas
            </button>
            <button 
                type="button" 
                wire:click="$set('filterPriority', 'urgent')" 
                class="btn btn-xs {{ $filterPriority === 'urgent' ? 'btn-error text-white' : 'btn-ghost text-error' }}"
            >
                Urgentes
            </button>
            <button 
                type="button" 
                wire:click="$set('filterPriority', 'important')" 
                class="btn btn-xs {{ $filterPriority === 'important' ? 'btn-warning' : 'btn-ghost text-warning' }}"
            >
                Importantes
            </button>
            <button 
                type="button" 
                wire:click="$set('filterPriority', 'normal')" 
                class="btn btn-xs {{ $filterPriority === 'normal' ? 'btn-primary' : 'btn-ghost' }}"
            >
                Informativas
            </button>
        </div>

        <div>
            <x-checkbox 
                label="Solo pendientes de acuse de recibo" 
                wire:model.live="onlyPendingAck" 
                class="checkbox-warning checkbox-sm" 
            />
        </div>
    </div>

    {{-- LISTA DE CIRCULARES --}}
    <div class="space-y-4">
        @forelse ($announcementList as $item)
            @php
                $isAck = $item->isAcknowledgedBy(auth()->user());
                $borderClass = match($item->priority) {
                    'urgent' => 'border-l-4 border-l-error bg-error/5',
                    'important' => 'border-l-4 border-l-warning bg-warning/5',
                    default => 'border border-base-200 bg-base-100',
                };
            @endphp

            <x-card class="shadow-sm {{ $borderClass }} transition hover:shadow-md">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        {{-- ENCABEZADO DE LA CIRCULAR --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @if($item->priority === 'urgent')
                                <span class="badge badge-error text-white badge-xs font-bold uppercase tracking-wider">Urgente</span>
                            @elseif($item->priority === 'important')
                                <span class="badge badge-warning badge-xs font-bold uppercase tracking-wider">Importante</span>
                            @endif

                            @if($item->show_in_calendar)
                                <span class="badge badge-outline badge-xs gap-1 text-primary">
                                    <x-icon name="o-calendar" class="w-3 h-3" />
                                    En Calendario
                                </span>
                            @endif

                            @if($item->career)
                                <span class="badge badge-neutral badge-xs">{{ $item->career->name }}</span>
                            @elseif($item->subject)
                                <span class="badge badge-neutral badge-xs">{{ $item->subject->name }}</span>
                            @elseif($item->target === 'all')
                                <span class="badge badge-ghost badge-xs">Institucional / General</span>
                            @endif

                            <h3 class="text-lg font-bold text-base-content">{{ $item->title }}</h3>
                        </div>

                        {{-- METADATOS --}}
                        <div class="text-xs text-base-content/60 flex items-center gap-2 flex-wrap">
                            <span>Emisor: <strong>{{ $item->user->fullname ?? 'Dirección' }}</strong></span>
                            <span>•</span>
                            <span>Publicado el {{ $item->published_at ? $item->published_at->format('d/m/Y H:i') : $item->created_at->format('d/m/Y H:i') }}</span>
                            @if($item->start)
                                <span>•</span>
                                <span class="text-primary font-medium">Fecha: {{ $item->start->format('d/m/Y H:i') }}</span>
                            @endif
                            @if($item->expires_at)
                                <span>•</span>
                                <span class="text-warning">Vence: {{ $item->expires_at->format('d/m/Y') }}</span>
                            @endif
                        </div>

                        {{-- CONTENIDO --}}
                        <div class="pt-2 text-sm text-base-content/90 whitespace-pre-line leading-relaxed border-t border-base-200/60">
                            {{ $item->description }}
                        </div>
                    </div>

                    {{-- ACCIONES DE ACUSE DE RECIBO --}}
                    @if($item->requires_acknowledgment)
                        <div class="md:w-56 shrink-0 flex flex-col items-end justify-center pt-2 md:pt-0">
                            @if($isAck)
                                <div class="bg-success/10 border border-success/30 text-success p-2.5 rounded-xl text-center w-full space-y-1">
                                    <div class="flex items-center justify-center gap-1 font-bold text-xs">
                                        <x-icon name="o-check-badge" class="w-4 h-4" />
                                        <span>Notificado / Firmado</span>
                                    </div>
                                    <div class="text-[10px] opacity-80">Acuse de recibo registrado</div>
                                </div>
                            @else
                                <div class="w-full space-y-2 text-center bg-warning/10 border border-warning/30 p-2.5 rounded-xl">
                                    <div class="text-xs font-semibold text-warning">Requiere tu acuse de recibo</div>
                                    <x-button 
                                        label="Confirmar Notificación" 
                                        icon="o-check" 
                                        class="btn-warning btn-sm w-full font-bold" 
                                        wire:click="acknowledge({{ $item->id }})" 
                                        spinner="acknowledge({{ $item->id }})" 
                                    />
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="text-center py-12 text-base-content/50 border-2 border-dashed border-base-300 rounded-2xl bg-base-100">
                <x-icon name="o-megaphone" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <h4 class="font-bold text-base">No hay circulares para mostrar</h4>
                <p class="text-xs max-w-sm mx-auto mt-1">Todas las comunicaciones están al día o no coinciden con los filtros aplicados.</p>
            </div>
        @endforelse

        {{-- PAGINACIÓN --}}
        <div>
            {{ $announcementList->links() }}
        </div>
    </div>
</div>
