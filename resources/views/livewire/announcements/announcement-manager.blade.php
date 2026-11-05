<?php

use App\Enums\UserRole;
use App\Models\Career;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    // Search and filter
    public string $search = '';

    // Create Modal state
    public bool $showCreateModal = false;
    public string $title = '';
    public string $content = '';
    public string $priority = 'normal';
    public string $target_type = 'all';
    public ?int $career_id = null;
    public ?int $subject_id = null;
    public array $target_roles = ['student', 'tutor'];
    public bool $requires_acknowledgment = false;
    public bool $show_in_calendar = false;
    public ?string $start = null;
    public ?string $end = null;
    public ?string $expires_at = null;

    // Metrics Modal state
    public bool $showMetricsModal = false;
    public ?int $selectedAnnouncementId = null;

    // Career / subject pickers (search text + selection)
    public string $careerQuery = '';
    public string $subjectQuery = '';

    public function selectCareer(int $id): void
    {
        $this->career_id = $id;
        $this->careerQuery = '';
    }

    public function clearCareer(): void
    {
        $this->career_id = null;
        $this->careerQuery = '';
    }

    public function selectSubject(int $id): void
    {
        $this->subject_id = $id;
        $this->subjectQuery = '';
    }

    public function clearSubject(): void
    {
        $this->subject_id = null;
        $this->subjectQuery = '';
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'title',
            'content',
            'priority',
            'target_type',
            'career_id',
            'subject_id',
            'target_roles',
            'requires_acknowledgment',
            'show_in_calendar',
            'start',
            'end',
            'expires_at',
        ]);
        $this->priority = 'normal';
        $this->target_type = 'all';
        $this->target_roles = ['student', 'tutor'];
        $this->show_in_calendar = false;
        $this->careerQuery = '';
        $this->subjectQuery = '';
        $this->showCreateModal = true;
    }

    public function toggleRole(string $role): void
    {
        $this->target_roles = in_array($role, $this->target_roles, true)
            ? array_values(array_diff($this->target_roles, [$role]))
            : [...$this->target_roles, $role];
    }

    public function createAnnouncement(): void
    {
        $this->validate([
            'title' => 'required|string|max:150',
            'content' => 'required|string|min:5',
            'priority' => 'required|in:normal,important,urgent',
            'target_type' => 'required|in:all,career,subject,role',
            'career_id' => 'nullable|required_if:target_type,career|exists:careers,id',
            'subject_id' => 'nullable|required_if:target_type,subject|exists:subjects,id',
            'start' => 'nullable|required_if:show_in_calendar,true|date',
            'end' => 'nullable|date|after_or_equal:start',
            'expires_at' => 'nullable|date',
        ]);

        $color = match ($this->priority) {
            'urgent' => '#DC2626',
            'important' => '#F59E0B',
            default => '#0284C7',
        };

        $startDate = $this->show_in_calendar && $this->start ? Carbon::parse($this->start) : null;
        $endDate = $this->show_in_calendar && $this->end
            ? Carbon::parse($this->end)
            : ($startDate ? (clone $startDate)->addHours(2) : null);

        Event::create([
            'user_id' => Auth::id(),
            'type' => 'announcement',
            'title' => $this->title,
            'description' => $this->content,
            'color' => $color,
            'priority' => $this->priority,
            'target' => $this->target_type,
            'career_id' => $this->target_type === 'career' ? $this->career_id : null,
            'subject_id' => $this->target_type === 'subject' ? $this->subject_id : null,
            'target_roles' => ! empty($this->target_roles) ? array_values($this->target_roles) : null,
            'requires_acknowledgment' => $this->requires_acknowledgment,
            'show_in_calendar' => $this->show_in_calendar,
            'start' => $startDate,
            'end' => $endDate,
            'published_at' => now(),
            'expires_at' => $this->expires_at ?: null,
        ]);

        $this->showCreateModal = false;
        $this->success('Circular institucional publicada con éxito.');
        $this->resetPage();
    }

    public function deleteAnnouncement(int $id): void
    {
        $ann = Event::announcements()->find($id);
        if ($ann) {
            $ann->delete();
            $this->warning('Circular eliminada.');
        }
    }

    public function viewMetrics(int $id): void
    {
        $this->selectedAnnouncementId = $id;
        $this->showMetricsModal = true;
    }

    public function selectedAnnouncement(): ?Event
    {
        if (! $this->selectedAnnouncementId) {
            return null;
        }

        return Event::with(['career', 'subject', 'readers'])->find($this->selectedAnnouncementId);
    }

    public function computeRecipientsFor(Event $ann): Collection
    {
        $query = User::query()->where('enabled', true);

        if (! empty($ann->target_roles)) {
            $query->whereIn('role', $ann->target_roles);
        }

        if ($ann->target === 'career' && $ann->career_id) {
            $studentIds = User::whereHas('careers', fn ($c) => $c->where('careers.id', $ann->career_id))->pluck('id');
            $tutorIds = collect();
            if (empty($ann->target_roles) || in_array('tutor', $ann->target_roles, true)) {
                $tutorIds = User::whereHas('students', fn ($s) => $s->whereIn('users.id', $studentIds))->pluck('id');
            }
            $targetUserIds = $studentIds->concat($tutorIds)->unique();
            $query->whereIn('id', $targetUserIds);
        } elseif ($ann->target === 'subject' && $ann->subject_id) {
            $studentIds = Enrollment::where('subject_id', $ann->subject_id)->where('status', 'active')->pluck('user_id');
            $tutorIds = collect();
            if (empty($ann->target_roles) || in_array('tutor', $ann->target_roles, true)) {
                $tutorIds = User::whereHas('students', fn ($s) => $s->whereIn('users.id', $studentIds))->pluck('id');
            }
            $targetUserIds = $studentIds->concat($tutorIds)->unique();
            $query->whereIn('id', $targetUserIds);
        }

        return $query->select(['id', 'firstname', 'lastname', 'name', 'email', 'role'])->orderBy('lastname')->get();
    }

    public function announcements(): LengthAwarePaginator
    {
        return Event::query()
            ->announcements()
            ->with(['user', 'career', 'subject', 'readers'])
            ->when($this->search, function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->latest('published_at')
            ->paginate(10);
    }

    public function with(): array
    {
        return [
            'announcementsList' => $this->announcements(),
            'selectedCareer' => $this->career_id ? Career::find($this->career_id) : null,
            'selectedSubject' => $this->subject_id ? Subject::with('career:id,name')->find($this->subject_id) : null,
            'careerResults' => $this->showCreateModal && $this->target_type === 'career' && ! $this->career_id
                ? Career::where('name', 'like', "%{$this->careerQuery}%")->orderBy('name')->limit(8)->get()
                : collect(),
            'subjectResults' => $this->showCreateModal && $this->target_type === 'subject' && ! $this->subject_id
                ? Subject::with('career:id,name')
                    ->where(function ($q) {
                        $q->where('name', 'like', "%{$this->subjectQuery}%")
                            ->orWhereHas('career', fn ($c) => $c->where('name', 'like', "%{$this->subjectQuery}%"));
                    })
                    ->orderBy('name')->limit(8)->get()
                : collect(),
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Gestión de Circulares" subtitle="Monitoreo y emisión de comunicaciones institucionales con seguimiento de lectura" separator progress-indicator>
        <x-slot:middle class="!justify-end flex items-center gap-3">
            <x-input 
                placeholder="Buscar comunicado..." 
                wire:model.live.debounce.300ms="search" 
                icon="o-magnifying-glass" 
                clear 
                class="input-sm"
            />
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Ver Muro de Circulares" icon="o-eye" class="btn-outline btn-sm" link="/announcements" />
            <x-button label="Nueva Circular" icon="o-plus" class="btn-primary btn-sm" wire:click="openCreateModal" />
        </x-slot:actions>
    </x-header>

    {{-- TABLA DE CIRCULARES --}}
    <x-card class="bg-base-100 shadow-sm border border-base-200">
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead class="bg-base-200/50">
                    <tr>
                        <th>Fecha / Prioridad</th>
                        <th>Título</th>
                        <th>Alcance</th>
                        <th>Destinatarios</th>
                        <th>Calendario</th>
                        <th>Acuse Requerido</th>
                        <th>Seguimiento</th>
                        <th class="text-right w-24">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($announcementsList as $ann)
                        @php
                            $readsCount = $ann->readers->whereNotNull('pivot.read_at')->count();
                            $ackCount = $ann->readers->whereNotNull('pivot.acknowledged_at')->count();
                        @endphp
                        <tr class="hover:bg-base-200/40">
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-xs">{{ $ann->published_at ? $ann->published_at->format('d/m/Y H:i') : $ann->created_at->format('d/m/Y H:i') }}</div>
                                @if($ann->priority === 'urgent')
                                    <span class="badge badge-error text-white badge-xs">Urgente</span>
                                @elseif($ann->priority === 'important')
                                    <span class="badge badge-warning badge-xs">Importante</span>
                                @else
                                    <span class="badge badge-ghost badge-xs">Normal</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-bold text-sm">{{ $ann->title }}</div>
                                <div class="text-[11px] text-base-content/60 truncate max-w-xs">{{ Str::limit($ann->description, 60) }}</div>
                            </td>
                            <td>
                                @if($ann->target === 'all')
                                    <span class="badge badge-outline badge-sm">Institucional (Global)</span>
                                @elseif($ann->target === 'career')
                                    <span class="badge badge-neutral badge-sm">{{ $ann->career->name ?? 'Carrera' }}</span>
                                @elseif($ann->target === 'subject')
                                    <span class="badge badge-neutral badge-sm">{{ $ann->subject->name ?? 'Materia' }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ ucfirst($ann->target) }}</span>
                                @endif
                            </td>
                            <td class="text-xs">
                                @if(!empty($ann->target_roles))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($ann->target_roles as $r)
                                            <span class="badge badge-ghost badge-xs">{{ ucfirst($r) }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-base-content/50 italic">Todos</span>
                                @endif
                            </td>
                            <td>
                                @if($ann->show_in_calendar)
                                    <span class="badge badge-primary badge-outline badge-xs gap-1">
                                        <x-icon name="o-calendar" class="w-3 h-3" />
                                        {{ $ann->start ? $ann->start->format('d/m H:i') : 'Sí' }}
                                    </span>
                                @else
                                    <span class="badge badge-ghost badge-xs">No</span>
                                @endif
                            </td>
                            <td>
                                @if($ann->requires_acknowledgment)
                                    <span class="badge badge-warning text-warning-content badge-xs font-semibold">Sí (Obligatorio)</span>
                                @else
                                    <span class="badge badge-ghost badge-xs">Informativo</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-xs space-y-0.5">
                                    <div class="flex items-center gap-1 text-primary font-medium">
                                        <x-icon name="o-eye" class="w-3.5 h-3.5" />
                                        <span>{{ $readsCount }} lecturas</span>
                                    </div>
                                    @if($ann->requires_acknowledgment)
                                        <div class="flex items-center gap-1 text-success font-medium">
                                            <x-icon name="o-check-circle" class="w-3.5 h-3.5" />
                                            <span>{{ $ackCount }} acuses</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button 
                                        icon="o-chart-bar" 
                                        class="btn-ghost btn-xs btn-square" 
                                        tooltip="Ver lecturas y destinatarios" 
                                        wire:click="viewMetrics({{ $ann->id }})" 
                                    />
                                    <x-button 
                                        icon="o-trash" 
                                        class="btn-error btn-xs btn-square" 
                                        tooltip="Eliminar" 
                                        wire:confirm="¿Seguro que deseas eliminar esta circular?" 
                                        wire:click="deleteAnnouncement({{ $ann->id }})" 
                                        spinner="deleteAnnouncement({{ $ann->id }})" 
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-base-content/50">
                                No se encontraron circulares registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4">
            {{ $announcementsList->links() }}
        </div>
    </x-card>

    @teleport('body')
        {{-- MODAL CREAR CIRCULAR --}}
        <x-modal wire:model="showCreateModal" title="Nueva circular institucional" separator class="backdrop-blur" box-class="max-w-5xl">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                {{-- COLUMNA IZQUIERDA: MENSAJE Y PRIORIDAD / VENCIMIENTO --}}
                <div class="lg:col-span-3 space-y-4">
                    <x-input
                        label="Título"
                        icon="o-megaphone"
                        wire:model="title"
                        placeholder="Ej. Reunión de familias - 2do trimestre"
                    />

                    <x-textarea
                        label="Mensaje"
                        wire:model="content"
                        placeholder="Escribe aquí el comunicado..."
                        rows="8"
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center bg-base-200/30 p-3 rounded-xl border border-base-200">
                        {{-- Prioridad --}}
                        <div class="space-y-1.5">
                            <div class="text-xs font-semibold text-base-content/70">Prioridad</div>
                            <div class="join w-full">
                                <button type="button" wire:click="$set('priority', 'normal')"
                                    class="btn btn-sm join-item flex-1 gap-1 {{ $priority === 'normal' ? 'btn-info text-info-content' : 'btn-outline btn-info' }}">
                                    <x-icon name="o-information-circle" class="w-4 h-4" /> Normal
                                </button>
                                <button type="button" wire:click="$set('priority', 'important')"
                                    class="btn btn-sm join-item flex-1 gap-1 {{ $priority === 'important' ? 'btn-warning' : 'btn-outline btn-warning' }}">
                                    <x-icon name="o-exclamation-triangle" class="w-4 h-4" /> Importante
                                </button>
                                <button type="button" wire:click="$set('priority', 'urgent')"
                                    class="btn btn-sm join-item flex-1 gap-1 {{ $priority === 'urgent' ? 'btn-error text-error-content' : 'btn-outline btn-error' }}">
                                    <x-icon name="o-bell-alert" class="w-4 h-4" /> Urgente
                                </button>
                            </div>
                        </div>

                        {{-- Vencimiento --}}
                        <div>
                            <x-input
                                label="Vence el (opcional)"
                                icon="o-calendar-days"
                                type="date"
                                wire:model="expires_at"
                            />
                        </div>
                    </div>
                </div>

                {{-- COLUMNA DERECHA: ALCANCE, DESTINATARIOS Y CONFIGURACIONES --}}
                <div class="lg:col-span-2 space-y-5 rounded-xl bg-base-200/40 p-4">
                    {{-- Destinatarios: alcance --}}
                    <div class="space-y-1.5">
                        <div class="text-xs font-semibold text-base-content/70">Enviar a</div>
                        <div class="join w-full">
                            <button type="button" wire:click="$set('target_type', 'all')"
                                class="btn btn-sm join-item flex-1 gap-1 {{ $target_type === 'all' ? 'btn-primary' : 'btn-outline' }}">
                                <x-icon name="o-building-library" class="w-4 h-4" /> Todos
                            </button>
                            <button type="button" wire:click="$set('target_type', 'career')"
                                class="btn btn-sm join-item flex-1 gap-1 {{ $target_type === 'career' ? 'btn-primary' : 'btn-outline' }}">
                                <x-icon name="o-academic-cap" class="w-4 h-4" /> Carrera
                            </button>
                            <button type="button" wire:click="$set('target_type', 'subject')"
                                class="btn btn-sm join-item flex-1 gap-1 {{ $target_type === 'subject' ? 'btn-primary' : 'btn-outline' }}">
                                <x-icon name="o-book-open" class="w-4 h-4" /> Materia
                            </button>
                        </div>

                        @if($target_type === 'career')
                            @if($selectedCareer)
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-primary/40 bg-primary/10 px-3 py-2">
                                    <span class="flex items-center gap-2 text-sm font-medium">
                                        <x-icon name="o-academic-cap" class="w-4 h-4 text-primary" />
                                        {{ $selectedCareer->name }}
                                    </span>
                                    <button type="button" wire:click="clearCareer" class="btn btn-ghost btn-xs btn-circle" aria-label="Quitar carrera">
                                        <x-icon name="o-x-mark" class="w-4 h-4" />
                                    </button>
                                </div>
                            @else
                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <x-input
                                        icon="o-magnifying-glass"
                                        wire:model.live.debounce.250ms="careerQuery"
                                        placeholder="Buscar carrera..."
                                        autocomplete="off"
                                        @focus="open = true"
                                    />
                                    <ul x-show="open" x-cloak class="menu absolute z-20 mt-1 w-full max-h-56 flex-nowrap overflow-y-auto rounded-lg border border-base-content/10 bg-base-100 p-1 shadow-xl">
                                        @forelse($careerResults as $careerOption)
                                            <li wire:key="career-option-{{ $careerOption->id }}">
                                                <button type="button" wire:click="selectCareer({{ $careerOption->id }})">
                                                    <x-icon name="o-academic-cap" class="w-4 h-4 opacity-60" />
                                                    {{ $careerOption->name }}
                                                </button>
                                            </li>
                                        @empty
                                            <li class="px-3 py-2 text-xs text-base-content/50">Sin resultados</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @endif
                        @elseif($target_type === 'subject')
                            @if($selectedSubject)
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-primary/40 bg-primary/10 px-3 py-2">
                                    <span class="flex items-center gap-2 text-sm font-medium">
                                        <x-icon name="o-book-open" class="w-4 h-4 text-primary" />
                                        <span>
                                            {{ $selectedSubject->name }}
                                            <span class="block text-xs font-normal text-base-content/60">{{ $selectedSubject->career->name ?? '' }}</span>
                                        </span>
                                    </span>
                                    <button type="button" wire:click="clearSubject" class="btn btn-ghost btn-xs btn-circle" aria-label="Quitar materia">
                                        <x-icon name="o-x-mark" class="w-4 h-4" />
                                    </button>
                                </div>
                            @else
                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <x-input
                                        icon="o-magnifying-glass"
                                        wire:model.live.debounce.250ms="subjectQuery"
                                        placeholder="Buscar materia o carrera..."
                                        autocomplete="off"
                                        @focus="open = true"
                                    />
                                    <ul x-show="open" x-cloak class="menu absolute z-20 mt-1 w-full max-h-56 flex-nowrap overflow-y-auto rounded-lg border border-base-content/10 bg-base-100 p-1 shadow-xl">
                                        @forelse($subjectResults as $subjectOption)
                                            <li wire:key="subject-option-{{ $subjectOption->id }}">
                                                <button type="button" wire:click="selectSubject({{ $subjectOption->id }})">
                                                    <x-icon name="o-book-open" class="w-4 h-4 opacity-60" />
                                                    <span>
                                                        {{ $subjectOption->name }}
                                                        <span class="block text-xs text-base-content/60">{{ $subjectOption->career->name ?? '' }}</span>
                                                    </span>
                                                </button>
                                            </li>
                                        @empty
                                            <li class="px-3 py-2 text-xs text-base-content/50">Sin resultados</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Destinatarios: roles --}}
                    <div class="space-y-1.5">
                        <div class="text-xs font-semibold text-base-content/70">Dirigido a</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([
                                'student' => ['Estudiantes', 'o-user'],
                                'tutor' => ['Familias', 'o-user-group'],
                                'teacher' => ['Docentes', 'o-briefcase'],
                            ] as $roleKey => [$roleLabel, $roleIcon])
                                @php $isSelectedRole = in_array($roleKey, $target_roles, true); @endphp
                                <button type="button" wire:click="toggleRole('{{ $roleKey }}')"
                                    class="btn btn-sm gap-1 {{ $isSelectedRole ? 'btn-success text-success-content' : 'btn-outline' }}">
                                    <x-icon :name="$isSelectedRole ? 'o-check-circle' : $roleIcon" class="w-4 h-4" />
                                    {{ $roleLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Pedir acuse de recibo --}}
                    <div class="rounded-xl border border-base-200 bg-base-100 p-3">
                        <x-toggle
                            label="Pedir acuse de recibo"
                            hint="Exige a estudiantes o tutores confirmar lectura de la circular"
                            right
                            wire:model="requires_acknowledgment"
                            class="toggle-warning"
                        />
                    </div>

                    {{-- INTEGRACIÓN CON CALENDARIO --}}
                    <div class="rounded-xl border border-base-200 bg-base-100 p-3 space-y-3">
                        <x-toggle
                            label="Mostrar en calendario escolar"
                            hint="Aparecerá en la cuadrícula mensual de eventos y en la lista de actividades del mes."
                            right
                            wire:model.live="show_in_calendar"
                            class="toggle-primary"
                        />

                        @if($show_in_calendar)
                            <div class="grid grid-cols-1 gap-3 pt-2 border-t border-base-200">
                                <x-input
                                    label="Fecha y hora de inicio"
                                    type="datetime-local"
                                    wire:model="start"
                                />
                                <x-input
                                    label="Fecha y hora de fin (opcional)"
                                    type="datetime-local"
                                    wire:model="end"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" icon="o-x-mark" @click="$wire.showCreateModal = false" />
                <x-button label="Publicar" icon="o-paper-airplane" class="btn-primary" wire:click="createAnnouncement" spinner="createAnnouncement" />
            </x-slot:actions>
        </x-modal>

        {{-- MODAL MÉTRICAS Y DESTINATARIOS --}}
        @php $selectedAnn = $this->selectedAnnouncement(); @endphp
        <x-modal wire:model="showMetricsModal" title="Lecturas y Acuses de Recibo" separator class="backdrop-blur" box-class="max-w-4xl">
            @if($selectedAnn)
                @php
                    $targetUsers = $this->computeRecipientsFor($selectedAnn);
                    $pivots = $selectedAnn->readers->keyBy('id');
                    $totalTarget = $targetUsers->count();
                    $totalRead = $targetUsers->filter(fn($u) => isset($pivots[$u->id]) && $pivots[$u->id]->pivot->read_at)->count();
                    $totalAck = $targetUsers->filter(fn($u) => isset($pivots[$u->id]) && $pivots[$u->id]->pivot->acknowledged_at)->count();
                    $readPct = $totalTarget > 0 ? round(($totalRead / $totalTarget) * 100) : 0;
                    $ackPct = $totalTarget > 0 ? round(($totalAck / $totalTarget) * 100) : 0;
                @endphp

                <div class="space-y-4">
                    {{-- STATS CARDS --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="bg-base-200/50 p-3 rounded-xl border border-base-200 text-center">
                            <div class="text-xs text-base-content/60">Destinatarios Totales</div>
                            <div class="text-2xl font-black text-primary">{{ $totalTarget }}</div>
                        </div>
                        <div class="bg-base-200/50 p-3 rounded-xl border border-base-200 text-center">
                            <div class="text-xs text-base-content/60">Lecturas Confirmadas</div>
                            <div class="text-2xl font-black text-info">{{ $totalRead }} ({{ $readPct }}%)</div>
                        </div>
                        <div class="bg-base-200/50 p-3 rounded-xl border border-base-200 text-center">
                            <div class="text-xs text-base-content/60">Acuses de Recibo</div>
                            <div class="text-2xl font-black {{ $selectedAnn->requires_acknowledgment ? 'text-success' : 'text-base-content/40' }}">
                                {{ $totalAck }} ({{ $ackPct }}%)
                            </div>
                        </div>
                    </div>

                    {{-- TABLA DE DESTINATARIOS --}}
                    <div class="border border-base-200 rounded-lg overflow-x-auto max-h-96">
                        <table class="table table-xs w-full">
                            <thead class="bg-base-200/60 sticky top-0">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Leído</th>
                                    <th>Acuse de Recibo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($targetUsers as $usr)
                                    @php
                                        $p = $pivots[$usr->id] ?? null;
                                        $readAt = $p?->pivot->read_at ? \Carbon\Carbon::parse($p->pivot->read_at)->format('d/m/Y H:i') : null;
                                        $ackAt = $p?->pivot->acknowledged_at ? \Carbon\Carbon::parse($p->pivot->acknowledged_at)->format('d/m/Y H:i') : null;
                                    @endphp
                                    <tr class="hover:bg-base-200/30">
                                        <td>
                                            <div class="font-semibold">{{ $usr->lastname }}, {{ $usr->firstname }}</div>
                                            <div class="text-[10px] text-base-content/60">DNI: {{ $usr->name }} | {{ $usr->email }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline badge-xs capitalize">{{ $usr->role }}</span>
                                        </td>
                                        <td>
                                            @if($readAt)
                                                <span class="text-success flex items-center gap-1 font-medium">
                                                    <x-icon name="o-check" class="w-3.5 h-3.5" /> {{ $readAt }}
                                                </span>
                                            @else
                                                <span class="text-base-content/40 italic">No leído</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($ackAt)
                                                <span class="badge badge-success text-white badge-xs">
                                                    Confirmado {{ $ackAt }}
                                                </span>
                                            @elseif($selectedAnn->requires_acknowledgment)
                                                <span class="badge badge-warning badge-xs">Pendiente</span>
                                            @else
                                                <span class="text-base-content/40 text-[10px]">No requerido</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-6 text-base-content/50">
                                            No se encontraron destinatarios directos calculados.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Cerrar" @click="$wire.showMetricsModal = false" />
                </x-slot:actions>
            @endif
        </x-modal>
    @endteleport
</div>
