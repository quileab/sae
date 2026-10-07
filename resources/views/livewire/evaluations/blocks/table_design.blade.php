@php
    use App\Services\Evaluations\ErDiagramLayout;

    $bands = ErDiagramLayout::bands(
        $block['tables'] ?? [],
        $block['relation_pairs'] ?? [],
        $block['layout'] ?? null
    );
@endphp

<div
    x-data="tableDesign({
        blockId: @js($block['id']),
        attributes: @js($block['attributes'] ?? []),
        tables: @js($block['tables'] ?? []),
        pairs: @js($block['relation_pairs'] ?? []),
        relationTypes: @js($block['relation_types'] ?? ['1:1', '1:N', 'N:N'])
    })"
    class="space-y-6"
>
    <div class="alert alert-info shadow-sm py-2 text-sm">
        <x-icon name="o-light-bulb" class="w-5 h-5" />
        <span>
            Distribuí los atributos disponibles entre las tablas. Cada celda lleva
            <code class="font-mono">[&nbsp;]</code> si es un atributo común,
            <code class="font-mono">[PK]</code> si es clave primaria y
            <code class="font-mono">[FK]</code> si es clave foránea.
            Las claves primarias se llaman <strong>ID</strong>.
        </span>
    </div>

    {{-- Progress: unused attributes and empty cells, as in the source exercise --}}
    <div class="flex flex-wrap gap-2">
        <span class="badge badge-ghost gap-1">
            <x-icon name="o-swatch" class="w-3.5 h-3.5" />
            Atributos sin usar: <strong class="font-mono" x-text="remainingAttributes().length"></strong>
        </span>
        <span class="badge badge-ghost gap-1">
            <x-icon name="o-squares-2x2" class="w-3.5 h-3.5" />
            Celdas vacías: <strong class="font-mono" x-text="emptyCells()"></strong>
        </span>
    </div>

    {{-- Remaining attributes --}}
    <div class="bg-base-200 p-3 rounded-lg border border-base-300">
        <div class="text-xs font-bold uppercase text-base-content/70 mb-2">
            Atributos sin usar (<span class="font-mono" x-text="remainingAttributes().length"></span>)
        </div>
        <div class="flex flex-wrap gap-2 min-h-8">
            <template x-for="(attribute, index) in remainingAttributes()" :key="'attr' + index">
                <code class="px-2 py-1 bg-base-100 border border-base-300 rounded text-xs font-mono font-semibold text-base-content"
                      x-text="attribute"></code>
            </template>
            <span x-show="remainingAttributes().length === 0"
                  class="text-xs italic text-base-content/50">
                Todos los atributos han sido asignados.
            </span>
        </div>
    </div>

    {{-- Diagram: connectors render above the band they point at --}}
    <div class="space-y-4">
        @foreach($bands as $band)
            @if($band['connectors'])
                <div class="flex flex-wrap justify-center gap-3 py-1">
                    @foreach($band['connectors'] as $pair)
                        <div class="flex items-center gap-2 bg-base-100 px-3 py-1.5 rounded border border-base-300"
                             x-data
                             x-bind:class="relations.find(r => r.clave === @js($pair['clave']))?.type
                                ? 'border-primary' : ''">
                            <span class="text-xs font-semibold text-base-content/70">
                                <span class="font-mono">{{ strtoupper(str_replace('_', ' ', $pair['from'])) }}</span>
                                <x-icon name="o-arrow-down" class="w-3 h-3 inline" />
                                <span class="font-mono">{{ strtoupper(str_replace('_', ' ', $pair['to'])) }}</span>
                            </span>
                            <select
                                class="select select-bordered select-xs font-mono w-24"
                                :aria-label="'Relación ' + relationLabel(@js($pair['clave']))"
                                x-model="relations.find(r => r.clave === @js($pair['clave'])).type"
                                @change="saveState()"
                            >
                                <option value="">— tipo —</option>
                                <template x-for="type in relationTypes" :key="type">
                                    <option :value="type" x-text="type"></option>
                                </template>
                            </select>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 {{ count($band['tables']) > 1 ? 'md:grid-cols-2' : '' }}">
                @foreach($band['tables'] as $tableName)
                    @php
                        $slotCount = collect($block['tables'] ?? [])->firstWhere('name', $tableName)['rows'] ?? 0;
                        $wide = $slotCount >= 5;
                    @endphp
                    <div class="card bg-base-100 rounded-lg border-2 border-primary/40 shadow-sm overflow-hidden">
                        <div class="bg-primary text-primary-content px-4 py-2 font-bold text-center text-sm tracking-wide">
                            TABLA: {{ strtoupper(str_replace('_', ' ', $tableName)) }}
                        </div>
                        <div class="p-3 space-y-2 bg-base-200/40">
                            <template x-for="(row, index) in slotsFor(@js($tableName))" :key="index">
                                <div class="flex items-center gap-2">
                                    <select
                                        class="select select-bordered select-xs font-mono font-bold w-20 shrink-0 text-center"
                                        :aria-label="'Clave de la fila ' + (index + 1)"
                                        x-model="columnsFor(@js($tableName))[index].type"
                                        @change="saveState()"
                                    >
                                        <option value=""> [&nbsp;]</option>
                                        <option value="PK">[PK]</option>
                                        <option value="FK">[FK]</option>
                                    </select>
                                    <select
                                        class="select select-bordered select-xs flex-1 font-mono"
                                        :aria-label="'Atributo de la fila ' + (index + 1)"
                                        x-model="columnsFor(@js($tableName))[index].name"
                                        @change="saveState()"
                                    >
                                        <option value="">— atributo —</option>
                                        <template x-for="attribute in optionsFor(columnsFor(@js($tableName))[index].name)"
                                                  :key="'sel_' + index + attribute">
                                            <option :value="attribute" x-text="attribute"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    @if($bands === [])
        <x-alert icon="o-exclamation-triangle" class="alert-warning text-sm">
            Este ejercicio todavía no tiene tablas configuradas.
        </x-alert>
    @endif
</div>