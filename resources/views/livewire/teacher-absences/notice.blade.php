<?php

use Livewire\Volt\Component;
use App\Models\JustifiedAbsence;
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $selectedTab = 'new-tab';

    public ?int $editingId = null;
    public string $article = '';
    public string $custom_article = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $description = '';

    // Drawer para editar aviso existente
    public bool $drawer = false;

    public function mount(): void
    {
        $this->start_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
        $this->end_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
    }

    public function updatedStartDate($value): void
    {
        if ($this->end_date < $value) {
            $this->end_date = $value;
        }
    }

    public function submit(): void
    {
        $this->validateData();

        JustifiedAbsence::create([
            'user_id' => auth()->id(),
            'article' => $this->article,
            'custom_article' => $this->article === 'other' ? $this->custom_article : null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'status' => 'pending',
        ]);

        $this->reset(['article', 'custom_article', 'description']);
        $this->start_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
        $this->end_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();

        $this->selectedTab = 'my-absences-tab';
        $this->success('Aviso de ausencia registrado correctamente.');
    }

    public function openEdit(int $id): void
    {
        $absence = JustifiedAbsence::where('user_id', auth()->id())
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->editingId = $absence->id;
        $this->article = $absence->article ?? '';
        $this->custom_article = $absence->custom_article ?? '';
        $this->start_date = $absence->start_date->toDateString();
        $this->end_date = $absence->end_date->toDateString();
        $this->description = $absence->description ?? '';

        $this->drawer = true;
    }

    public function updateAbsence(): void
    {
        $this->validateData();

        $absence = JustifiedAbsence::where('user_id', auth()->id())
            ->where('id', $this->editingId)
            ->where('status', 'pending')
            ->firstOrFail();

        $absence->update([
            'article' => $this->article,
            'custom_article' => $this->article === 'other' ? $this->custom_article : null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
        ]);

        $this->drawer = false;
        $this->reset(['editingId', 'article', 'custom_article', 'description']);
        $this->start_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
        $this->end_date = Carbon::now('America/Argentina/Buenos_Aires')->toDateString();

        $this->success('Aviso de ausencia actualizado correctamente.');
    }

    protected function validateData(): void
    {
        $rules = [
            'article' => 'required|string',
            'custom_article' => 'required_if:article,other|nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ];

        $messages = [
            'article.required' => 'Debe seleccionar el artículo de licencia.',
            'custom_article.required_if' => 'Indique el número de artículo y su descripción.',
            'start_date.required' => 'La fecha de inicio es requerida.',
            'end_date.required' => 'La fecha de finalización es requerida.',
            'end_date.after_or_equal' => 'La fecha "Hasta" debe ser igual o posterior a "Desde".',
        ];

        $this->validate($rules, $messages);
    }

    public function cancelAbsence(int $id): void
    {
        $absence = JustifiedAbsence::where('user_id', auth()->id())
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $absence->delete();
        $this->success('Aviso de ausencia cancelado.');
    }

    public function with(): array
    {
        $articles = collect(JustifiedAbsence::ARTICLES)->map(fn ($label, $key) => [
            'id' => $key,
            'name' => $label,
        ])->values()->all();

        $myAbsences = JustifiedAbsence::where('user_id', auth()->id())
            ->latest('start_date')
            ->get();

        return [
            'articleOptions' => $articles,
            'myAbsences' => $myAbsences,
            'user' => auth()->user(),
        ];
    }
}; ?>

<div class="p-4 sm:p-6 space-y-6">
    <x-header title="Aviso de Ausencias Docente" subtitle="Informar ausencia al dictado de clases u otra actividad institucional" />

    {{-- Banner colapsable de información importante con efecto ping --}}
    <div x-data="{ openInfo: false }" class="border border-info/30 bg-info/10 rounded-xl overflow-hidden shadow-sm">
        <button 
            type="button" 
            @click="openInfo = !openInfo" 
            class="w-full flex items-center justify-between p-3.5 text-left hover:bg-info/15 transition-colors focus:outline-none cursor-pointer"
        >
            <div class="flex items-center gap-3">
                <div class="relative flex items-center justify-center">
                    <span class="absolute inline-flex h-4 w-4 rounded-full bg-info opacity-75 animate-ping"></span>
                    <x-icon name="o-information-circle" class="w-6 h-6 text-info shrink-0 relative" />
                </div>
                <div>
                    <span class="font-bold text-sm text-base-content">Información Importante sobre Licencias</span>
                    <span class="text-xs text-base-content/60 ml-2 hidden sm:inline">(Haga clic para ver los requisitos de presentación)</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-semibold text-info">
                <span x-text="openInfo ? 'Ocultar' : 'Ver requisitos'"></span>
                <x-icon name="o-chevron-down" class="w-4 h-4 transition-transform duration-200" ::class="openInfo ? 'rotate-180' : ''" />
            </div>
        </button>

        <div 
            x-show="openInfo" 
            x-collapse 
            class="px-4 pb-4 pt-1 border-t border-info/20 text-xs sm:text-sm text-base-content/80 space-y-2"
        >
            <ul class="list-disc list-inside space-y-1.5 pt-2">
                <li>La documentación que justifique su ausencia debe ser entregada en soporte papel en <strong>Secretaría dentro de las 72 horas</strong> del día de inicio de la licencia.</li>
                <li>Además, tendrá que comunicar a algún directivo o a la secretaría con tiempo suficiente (por WhatsApp, mensaje o llamada) para buscar reemplazante.</li>
            </ul>
        </div>
    </div>

    <x-tabs wire:model="selectedTab">
        {{-- Pestaña 1: Nuevo Aviso --}}
        <x-tab name="new-tab" label="Nuevo Aviso" icon="o-plus-circle">
            <div class="w-full pt-2">
                <x-card title="Registrar Aviso de Ausencia" shadow>
                    <x-form wire:submit="submit" class="space-y-4">
                        {{-- Datos del Docente (de sólo lectura) --}}
                        <div class="bg-base-200/60 p-3 rounded-lg space-y-2 border border-base-300">
                            <div class="text-xs uppercase font-bold text-base-content/70">Datos del Docente</div>
                            <div class="text-sm font-semibold">{{ $user->fullname }}</div>
                            <div class="text-xs text-base-content/70 flex flex-wrap gap-4">
                                <span><strong>DNI:</strong> {{ $user->id }}</span>
                                <span><strong>Email:</strong> {{ $user->email }}</span>
                            </div>
                        </div>

                        {{-- Selección de Artículo --}}
                        <div>
                            <x-select 
                                label="Artículo de Licencia" 
                                :options="$articleOptions" 
                                wire:model.live="article" 
                                placeholder="-- Seleccione el artículo --" 
                                icon="o-document-text" 
                                inline 
                            />
                        </div>

                        @if($article === 'other')
                            <x-input 
                                label="Indique el artículo y su descripción" 
                                wire:model="custom_article" 
                                placeholder="Ej. Art. especial por..." 
                                icon="o-pencil" 
                            />
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-input 
                                label="Desde" 
                                type="date" 
                                wire:model.live="start_date" 
                                icon="o-calendar" 
                            />
                            <x-input 
                                label="Hasta" 
                                type="date" 
                                wire:model="end_date" 
                                icon="o-calendar" 
                            />
                        </div>

                        <x-textarea 
                            label="Observaciones (opcional)" 
                            wire:model="description" 
                            placeholder="Aclaraciones sobre comisiones, materias afectadas o detalles de la licencia..." 
                            rows="3" 
                        />

                        <x-slot:actions>
                            <x-button 
                                label="Enviar Aviso" 
                                icon="o-paper-airplane" 
                                class="btn-primary w-full sm:w-auto" 
                                type="submit" 
                                spinner="submit" 
                            />
                        </x-slot:actions>
                    </x-form>
                </x-card>
            </div>
        </x-tab>

        {{-- Pestaña 2: Mis Avisos de Ausencia --}}
        <x-tab name="my-absences-tab" label="Mis Avisos de Ausencia ({{ $myAbsences->count() }})" icon="o-clipboard-document-list">
            <div class="pt-2">
                <x-card title="Mis Avisos de Ausencia" subtitle="Historial y estado de licencias solicitadas" shadow>
                    @if($myAbsences->isEmpty())
                        <div class="text-center py-10 text-base-content/60 border-2 border-dashed border-base-300 rounded-xl">
                            <x-icon name="o-clipboard-document-check" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                            <p class="text-sm font-medium">No has registrado avisos de ausencia recientemente.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr class="text-xs uppercase bg-base-200/50">
                                        <th>Fechas</th>
                                        <th>Artículo</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($myAbsences as $abs)
                                        <tr class="hover:bg-base-200/40">
                                            <td class="whitespace-nowrap font-medium text-xs">
                                                <div>{{ $abs->start_date->format('d/m/Y') }}</div>
                                                @if($abs->start_date != $abs->end_date)
                                                    <div class="text-base-content/60 text-[11px]">al {{ $abs->end_date->format('d/m/Y') }}</div>
                                                @endif
                                            </td>
                                            <td class="text-xs">
                                                <div class="font-semibold">{{ $abs->article_label }}</div>
                                                @if($abs->description)
                                                    <div class="text-base-content/70 italic text-[11px] line-clamp-2">{{ $abs->description }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $abs->status_badge }} badge-xs sm:badge-sm">
                                                    {{ $abs->status_label }}
                                                </span>
                                            </td>
                                            <td class="text-right whitespace-nowrap space-x-1">
                                                @if($abs->status === 'pending')
                                                    <x-button 
                                                        icon="o-pencil" 
                                                        class="btn-ghost btn-xs text-info tooltip tooltip-left" 
                                                        data-tip="Editar aviso"
                                                        wire:click="openEdit({{ $abs->id }})" 
                                                        spinner="openEdit({{ $abs->id }})" 
                                                    />
                                                    <x-button 
                                                        icon="o-trash" 
                                                        class="btn-ghost btn-xs text-error tooltip tooltip-left" 
                                                        data-tip="Cancelar aviso"
                                                        wire:click="cancelAbsence({{ $abs->id }})" 
                                                        wire:confirm="¿Seguro que deseas cancelar este aviso de ausencia?" 
                                                        spinner="cancelAbsence({{ $abs->id }})" 
                                                    />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>
        </x-tab>
    </x-tabs>

    {{-- Drawer para Editar Aviso del Docente --}}
    <x-qb-drawer wire:model="drawer" title="Editar Aviso de Ausencia" right separator with-close-button close-on-escape class="lg:w-1/3">
        <x-form wire:submit="updateAbsence" class="space-y-4">
            <x-select 
                label="Artículo de Licencia" 
                :options="$articleOptions" 
                wire:model.live="article" 
                icon="o-document-text" 
                inline 
            />

            @if($article === 'other')
                <x-input 
                    label="Indique el artículo y su descripción" 
                    wire:model="custom_article" 
                    placeholder="Ej. Art. especial por..." 
                    icon="o-pencil" 
                />
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-input 
                    label="Desde" 
                    type="date" 
                    wire:model.live="start_date" 
                    icon="o-calendar" 
                />
                <x-input 
                    label="Hasta" 
                    type="date" 
                    wire:model="end_date" 
                    icon="o-calendar" 
                />
            </div>

            <x-textarea 
                label="Observaciones (opcional)" 
                wire:model="description" 
                rows="4" 
            />

            <x-slot:actions>
                <div class="flex justify-between items-center w-full">
                    <x-button label="Cancelar" @click="$wire.drawer = false" class="btn-ghost" />
                    <x-button label="Guardar Cambios" class="btn-primary" type="submit" spinner="updateAbsence" icon="o-check" />
                </div>
            </x-slot:actions>
        </x-form>
    </x-qb-drawer>
</div>
