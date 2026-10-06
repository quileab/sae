<div x-data="tableDesign({
        blockId: @js($block['id']),
        attributes: @js($block['attributes'] ?? []),
        tables: @js($block['tables'] ?? [])
    })" class="space-y-4">

    <div class="alert alert-info shadow-sm py-2 text-sm">
        <x-icon name="o-information-circle" class="w-5 h-5" />
        <span>
            Distribuí los atributos disponibles entre las tablas, marcá la clave primaria (PK)
            y las foráneas (FK), y definí las relaciones al final.
        </span>
    </div>

    {{-- Available attributes --}}
    <div class="bg-base-200 p-3 rounded-lg border border-base-300">
        <div class="text-xs font-bold uppercase text-base-content/70 mb-2">
            Atributos disponibles (<span x-text="availableAttributes.length"></span>)
        </div>
        <div class="flex flex-wrap gap-2 min-h-8">
            <template x-for="(attribute, index) in availableAttributes" :key="index">
                <span class="badge badge-outline badge-sm" x-text="attribute"></span>
            </template>
            <span x-show="availableAttributes.length === 0"
                  class="text-xs italic text-base-content/50">
                Todos los atributos han sido asignados.
            </span>
        </div>
    </div>

    {{-- Tables --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template x-for="(table, tableIndex) in tables" :key="table.name">
            <div class="border border-primary/20 bg-base-100 rounded-lg shadow-sm overflow-hidden">
                <div class="bg-primary/10 px-3 py-2 font-bold text-center border-b border-primary/20 flex justify-between items-center gap-2">
                    <span class="text-sm truncate" x-text="table.name"></span>
                    <span class="badge badge-xs badge-primary shrink-0" x-text="table.rows + (table.rows === 1 ? ' fila' : ' filas')"></span>
                </div>
                <div class="p-3 space-y-2">
                    <template x-for="row in Number(table.rows)" :key="row">
                        <div class="flex gap-2 items-center">
                            <select x-model="studentTables[tableIndex].columns[row - 1].type"
                                    @change="saveState"
                                    class="select select-bordered select-xs w-20 shrink-0"
                                    aria-label="Tipo de clave">
                                <option value="">( )</option>
                                <option value="PK">PK</option>
                                <option value="FK">FK</option>
                            </select>
                            <select x-model="studentTables[tableIndex].columns[row - 1].name"
                                    @change="updateAvailable(); saveState()"
                                    class="select select-bordered select-xs flex-1"
                                    aria-label="Columna">
                                <option value="">-- Seleccionar --</option>
                                <template x-for="attribute in [...new Set([...availableAttributes, studentTables[tableIndex].columns[row - 1].name].filter(Boolean))]"
                                          :key="attribute">
                                    <option :value="attribute" x-text="attribute"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- Relations --}}
    <div class="mt-6 border border-base-300 rounded-lg bg-base-100 p-4">
        <div class="flex justify-between items-center mb-3">
            <div class="text-sm font-bold uppercase">Relaciones</div>
            <button type="button" @click="addRelation" class="btn btn-xs btn-outline btn-primary">
                <x-icon name="o-plus" class="w-3 h-3" />
                Agregar
            </button>
        </div>
        <div class="space-y-2">
            <template x-for="(relation, relationIndex) in relations" :key="relationIndex">
                <div class="flex flex-wrap gap-2 items-center bg-base-200 p-2 rounded">
                    <select x-model="relation.from" @change="saveState"
                            class="select select-bordered select-xs flex-1 min-w-24" aria-label="Tabla origen">
                        <option value="">-- Origen --</option>
                        <template x-for="table in tables" :key="table.name">
                            <option :value="table.name" x-text="table.name"></option>
                        </template>
                    </select>

                    <x-icon name="o-arrow-right" class="w-4 h-4 text-base-content/50 shrink-0" />

                    <select x-model="relation.to" @change="saveState"
                            class="select select-bordered select-xs flex-1 min-w-24" aria-label="Tabla destino">
                        <option value="">-- Destino --</option>
                        <template x-for="table in tables" :key="table.name">
                            <option :value="table.name" x-text="table.name"></option>
                        </template>
                    </select>

                    <select x-model="relation.type" @change="saveState"
                            class="select select-bordered select-xs w-20 shrink-0" aria-label="Cardinalidad">
                        <option value="1:1">1:1</option>
                        <option value="1:N">1:N</option>
                        <option value="N:N">N:N</option>
                    </select>

                    <button type="button" @click="removeRelation(relationIndex)"
                            class="btn btn-xs btn-ghost text-error shrink-0" aria-label="Quitar relación">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </template>
            <div x-show="relations.length === 0" class="text-xs text-center text-base-content/50 italic py-2">
                No se han definido relaciones.
            </div>
        </div>
    </div>
</div>