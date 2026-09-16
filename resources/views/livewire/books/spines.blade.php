<?php

use Livewire\Volt\Component;
use App\Models\Book;
use App\Services\BookSignatureService;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $idsInput = '';
    public array $selectedBookIds = [];
    public string $searchQuery = '';

    public function mount(): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403, 'No tienes permiso para generar tejuelos.');
        }

        // Si se pasan IDs por query param (ej: /books/spines?ids=1,2,3)
        $rawIds = request()->query('ids', '');
        if ($rawIds) {
            $this->idsInput = $rawIds;
            $this->parseIdsInput();
        }
    }

    public function parseIdsInput(): void
    {
        if (trim($this->idsInput) === '') {
            $this->selectedBookIds = [];
            return;
        }

        $ids = [];
        // Separar por comas, espacios o saltos de linea
        $tokens = preg_split('/[\s,\n\r]+/', trim($this->idsInput));

        foreach ($tokens as $token) {
            $token = trim($token);
            if (empty($token)) continue;

            // Manejar rangos como 1-5 o 10-15
            if (str_contains($token, '-')) {
                $range = explode('-', $token);
                if (count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1])) {
                    $start = min((int)$range[0], (int)$range[1]);
                    $end = max((int)$range[0], (int)$range[1]);
                    for ($i = $start; $i <= $end; $i++) {
                        $ids[] = $i;
                    }
                }
            } elseif (is_numeric($token)) {
                $ids[] = (int)$token;
            }
        }

        $this->selectedBookIds = array_values(array_unique(array_filter($ids)));

        if (!empty($this->selectedBookIds)) {
            $count = count($this->selectedBookIds);
            $this->success("Se procesaron {$count} ID(s) de libros.");
        } else {
            $this->warning("No se encontraron IDs válidos.");
        }
    }

    public function toggleBookSelection(int $id): void
    {
        if (in_array($id, $this->selectedBookIds)) {
            $this->selectedBookIds = array_values(array_filter($this->selectedBookIds, fn($i) => $i !== $id));
        } else {
            $this->selectedBookIds[] = $id;
        }

        $this->syncIdsInputFromSelected();
    }

    public function clearSelection(): void
    {
        $this->selectedBookIds = [];
        $this->idsInput = '';
    }

    protected function syncIdsInputFromSelected(): void
    {
        $this->idsInput = implode(', ', $this->selectedBookIds);
    }

    public function getSelectedBooksProperty(): mixed
    {
        if (empty($this->selectedBookIds)) {
            return collect();
        }

        return Book::whereIn('id', $this->selectedBookIds)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function getSearchBooksProperty(): mixed
    {
        if (strlen(trim($this->searchQuery)) < 2) {
            return collect();
        }

        $term = trim($this->searchQuery);

        return Book::where('title', 'like', "%{$term}%")
            ->orWhere('author', 'like', "%{$term}%")
            ->orWhere('signature', 'like', "%{$term}%")
            ->limit(10)
            ->get();
    }

    public function printSpinesUrl(): string
    {
        $idsString = implode(',', $this->selectedBookIds);
        return "/books/spines/print?ids={$idsString}";
    }
}; ?>

<div>
    <x-header title="Generador e Impresor de Tejuelos Físicos" subtitle="Seleccione o ingrese un listado de IDs para generar la grilla de etiquetas impresas para el lomo" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Volver a Libros" icon="o-arrow-left" link="/books" />
            <x-button 
                label="Imprimir Grilla de Tejuelos" 
                icon="o-printer" 
                class="btn-primary" 
                :link="!empty($selectedBookIds) ? '/books/spines/print?ids=' . implode(',', $selectedBookIds) : null" 
                target="_blank"
                external
                :disabled="empty($selectedBookIds)" 
            />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 1 Column: Input IDs & Quick Search -->
        <div class="space-y-6">
            <x-card title="1. Ingreso por IDs / Rangos" subtitle="Pegue IDs separados por comas o rangos (ej: 1, 2, 5-10)">
                <div class="space-y-3">
                    <x-textarea 
                        wire:model="idsInput" 
                        rows="4" 
                        placeholder="Ejemplo: 1, 2, 3, 5-12, 20" 
                        hint="Acepta IDs individuales y rangos (1-10)"
                    />
                    
                    <div class="flex gap-2">
                        <x-button label="Procesar IDs" icon="o-arrow-path" class="btn-primary flex-1" wire:click="parseIdsInput" />
                        <x-button label="Limpiar" icon="o-trash" class="btn-ghost text-error" wire:click="clearSelection" />
                    </div>
                </div>
            </x-card>

            <x-card title="2. O Buscar por Título/Autor" subtitle="Haga clic para agregar/quitar libros de la cola de tejuelos">
                <x-input 
                    wire:model.live.debounce.300ms="searchQuery" 
                    placeholder="Buscar libro en el catálogo..." 
                    icon="o-magnifying-glass"
                    clear
                />

                <div class="mt-3 divide-y divide-base-200 border border-base-200 rounded-lg max-h-60 overflow-y-auto">
                    @forelse($this->searchBooks as $b)
                        @php $isSelected = in_array($b->id, $selectedBookIds); @endphp
                        <div wire:click="toggleBookSelection({{ $b->id }})" 
                             class="p-2.5 hover:bg-base-200 cursor-pointer flex items-center justify-between transition-colors {{ $isSelected ? 'bg-primary/10 border-l-4 border-primary' : '' }}">
                            <div class="space-y-0.5">
                                <p class="text-xs font-bold text-base-content">#{{ $b->id }} - {{ $b->title }}</p>
                                <p class="text-[11px] text-base-content/60 font-mono">{{ $b->signature ?: 'Sin signatura' }}</p>
                            </div>
                            <x-badge :value="$isSelected ? 'Seleccionado' : '+ Agregar'" class="{{ $isSelected ? 'badge-primary' : 'badge-outline' }} text-[10px]" />
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-base-content/50">
                            @if(strlen(trim($searchQuery)) >= 2)
                                No se encontraron libros.
                            @else
                                Escriba al menos 2 letras para buscar.
                            @endif
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Right 2 Columns: Selected Books & Spine Grid Preview -->
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Vista Previa de Grilla de Tejuelos ({{ count($selectedBookIds) }} seleccionados)">
                @if($this->selectedBooks->isNotEmpty())
                    @php $service = app(BookSignatureService::class); @endphp
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-4 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-300 dark:border-slate-800 max-h-[500px] overflow-y-auto">
                        @foreach($this->selectedBooks as $bookItem)
                            @php
                                $label = $service->getSpineLabelData(
                                    null,
                                    null,
                                    $bookItem->author,
                                    $bookItem->title,
                                    '000',
                                    1
                                );

                                // Si el libro ya tiene signatura guardada en BD desglosarla
                                if ($bookItem->signature && str_contains($bookItem->signature, '-')) {
                                    $parts = explode('-', $bookItem->signature);
                                    $label['section'] = $parts[0] ?? $label['section'];
                                    $label['cdu'] = $parts[1] ?? $label['cdu'];
                                    $label['cutter'] = $parts[2] ?? $label['cutter'];
                                    $label['collection'] = $parts[3] ?? '000';
                                    $label['exemplar'] = $parts[4] ?? 'E01';
                                }
                            @endphp

                            <!-- Physical Spine Label Box (3x5 ratio) -->
                            <div class="bg-white border-2 border-dashed border-slate-400 rounded-md shadow-xs p-2 text-center text-slate-900 font-mono select-none flex flex-col justify-evenly h-40 text-xs">
                                <div class="text-[10px] font-bold text-slate-700 font-sans">#{{ $bookItem->id }}</div>
                                <div>
                                    <span class="text-[9px] font-black text-slate-400 block">SECCIÓN</span>
                                    <span class="font-bold text-primary text-sm">{{ $label['section'] }}</span>
                                </div>
                                <div>
                                    <span class="text-[9px] font-black text-slate-400 block">CDU</span>
                                    <span class="font-bold text-slate-800 text-xs">{{ $label['cdu'] }}</span>
                                </div>
                                <div>
                                    <span class="text-[9px] font-black text-slate-400 block">CUTTER</span>
                                    <span class="font-bold text-emerald-700 text-sm">{{ $label['cutter'] }}</span>
                                </div>
                                <div class="flex justify-between text-[10px] text-slate-600 font-bold">
                                    <span>{{ $label['collection'] }}</span>
                                    <span class="text-amber-700">{{ $label['exemplar'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-12 text-center text-base-content/60 space-y-3">
                        <x-icon name="o-qr-code" class="w-12 h-12 mx-auto text-base-content/30" />
                        <p class="font-bold">No hay tejuelos seleccionados para la grilla de impresión.</p>
                        <p class="text-xs">Pegue un listado de IDs a la izquierda o busque libros para agregar a la cola de impresión.</p>
                    </div>
                @endif

                <x-slot:actions>
                    <div class="flex justify-between w-full">
                        <x-button label="Limpiar Todo" icon="o-trash" class="btn-ghost" wire:click="clearSelection" :disabled="empty($selectedBookIds)" />
                        <x-button 
                            label="Imprimir Tejuelos en Hoja A4" 
                            icon="o-printer" 
                            class="btn-primary" 
                            :link="!empty($selectedBookIds) ? '/books/spines/print?ids=' . implode(',', $selectedBookIds) : null" 
                            target="_blank"
                            external
                            :disabled="empty($selectedBookIds)" 
                        />
                    </div>
                </x-slot:actions>
            </x-card>
        </div>
    </div>
</div>
