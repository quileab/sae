<div x-data="tableDesign({
        blockId: '{{ $block['id'] }}',
        attributes: {{ json_encode($block['attributes'] ?? []) }},
        tables: {{ json_encode($block['tables'] ?? []) }}
    })" class="space-y-4">

    <div class="alert alert-info shadow-sm py-2 text-sm mb-4">
        <x-icon name="o-information-circle" class="w-5 h-5" />
        <span>Arrastra o selecciona los atributos disponibles para cada tabla. Define las relaciones en la sección inferior.</span>
    </div>

    <!-- Atributos disponibles -->
    <div class="bg-base-200 p-3 rounded-lg border border-base-300">
        <div class="text-xs font-bold uppercase text-base-content/70 mb-2">Atributos Disponibles (<span x-text="availableAttributes.length"></span>)</div>
        <div class="flex flex-wrap gap-2 min-h-8">
            <template x-for="(attr, index) in availableAttributes" :key="index">
                <div class="badge badge-outline badge-sm cursor-pointer hover:bg-base-300" x-text="attr"></div>
            </template>
            <div x-show="availableAttributes.length === 0" class="text-xs italic text-base-content/50">Todos los atributos han sido asignados.</div>
        </div>
    </div>

    <!-- Tablas -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template x-for="(table, tIndex) in tables" :key="tIndex">
            <div class="border border-primary/20 bg-base-100 rounded-lg shadow-sm overflow-hidden">
                <div class="bg-primary/10 p-2 font-bold text-center border-b border-primary/20 flex justify-between items-center">
                    <span x-text="table.name" class="text-sm"></span>
                    <span class="badge badge-xs badge-primary" x-text="table.rows + ' filas'"></span>
                </div>
                <div class="p-2 space-y-2">
                    <template x-for="r in parseInt(table.rows)" :key="r">
                        <div class="flex gap-2 items-center">
                            <select x-model="studentTables[tIndex].columns[r-1].type" @change="saveState" class="select select-bordered select-xs w-20">
                                <option value="">[ ]</option>
                                <option value="PK">PK</option>
                                <option value="FK">FK</option>
                            </select>
                            <select x-model="studentTables[tIndex].columns[r-1].name" @change="updateAvailable(); saveState()" class="select select-bordered select-xs flex-1">
                                <option value="">-- Seleccionar --</option>
                                <template x-for="attr in [...availableAttributes, studentTables[tIndex].columns[r-1].name].filter(Boolean)" :key="attr">
                                    <option :value="attr" x-text="attr" :selected="studentTables[tIndex].columns[r-1].name === attr"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Relaciones -->
    <div class="mt-6 border border-base-300 rounded-lg bg-base-100 p-4">
        <div class="flex justify-between items-center mb-3">
            <div class="text-sm font-bold uppercase">Relaciones</div>
            <button type="button" @click="addRelation" class="btn btn-xs btn-outline btn-primary">
                <x-icon name="o-plus" class="w-3 h-3" /> Agregar
            </button>
        </div>
        <div class="space-y-2">
            <template x-for="(rel, rIndex) in relations" :key="rIndex">
                <div class="flex gap-2 items-center bg-base-200 p-2 rounded">
                    <select x-model="rel.from" @change="saveState" class="select select-bordered select-xs flex-1">
                        <option value="">-- Origen --</option>
                        <template x-for="t in tables" :key="t.name">
                            <option :value="t.name" x-text="t.name"></option>
                        </template>
                    </select>
                    <x-icon name="o-arrow-right" class="w-4 h-4 text-base-content/50" />
                    <select x-model="rel.to" @change="saveState" class="select select-bordered select-xs flex-1">
                        <option value="">-- Destino --</option>
                        <template x-for="t in tables" :key="t.name">
                            <option :value="t.name" x-text="t.name"></option>
                        </template>
                    </select>
                    <select x-model="rel.type" @change="saveState" class="select select-bordered select-xs w-20">
                        <option value="1:1">1:1</option>
                        <option value="1:N">1:N</option>
                        <option value="N:N">N:N</option>
                    </select>
                    <button type="button" @click="removeRelation(rIndex)" class="btn btn-xs btn-ghost text-error">
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

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('tableDesign', (config) => ({
            blockId: config.blockId,
            allAttributes: config.attributes || [],
            tables: config.tables || [],
            
            studentTables: [],
            relations: [],
            availableAttributes: [],

            init() {
                // Initialize studentTables state
                let existingAnswers = @this.get(`answers.${this.blockId}`);
                if (existingAnswers && typeof existingAnswers === 'object') {
                    this.studentTables = existingAnswers.tables || [];
                    this.relations = existingAnswers.relations || [];
                }

                if (this.studentTables.length === 0) {
                    this.tables.forEach(t => {
                        let cols = [];
                        for(let i=0; i<t.rows; i++) cols.push({type: '', name: ''});
                        this.studentTables.push({
                            name: t.name,
                            columns: cols
                        });
                    });
                }
                
                this.updateAvailable();
            },

            updateAvailable() {
                let used = [];
                this.studentTables.forEach(t => {
                    t.columns.forEach(c => {
                        if (c.name) used.push(c.name);
                    });
                });
                
                let avail = [...this.allAttributes];
                used.forEach(u => {
                    let idx = avail.indexOf(u);
                    if (idx > -1) avail.splice(idx, 1);
                });
                
                this.availableAttributes = avail;
            },

            addRelation() {
                this.relations.push({from: '', to: '', type: '1:N'});
                this.saveState();
            },

            removeRelation(idx) {
                this.relations.splice(idx, 1);
                this.saveState();
            },

            saveState() {
                this.updateAvailable();
                let state = {
                    tables: this.studentTables,
                    relations: this.relations
                };
                @this.set(`answers.${this.blockId}`, state);
                
                // Debounced save
                if(this.timeout) clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    @this.call('saveProgress');
                }, 1000);
            }
        }));
    });
</script>
@endpush
