/**
 * Relational schema design widget for evaluation table_design blocks.
 *
 * Lives in the bundle rather than in an @push('scripts') stack so it is
 * registered exactly once. A pushed <script> is re-inserted on every Livewire
 * morph, which re-runs the registration after Alpine has already booted.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('tableDesign', (config) => ({
        blockId: config.blockId,
        allAttributes: (config.attributes || []).filter(Boolean),
        tables: config.tables || [],

        studentTables: [],
        relations: [],
        availableAttributes: [],

        saveTimeout: null,

        init() {
            const answers = this.$wire.get(`answers.${this.blockId}`);

            if (answers && typeof answers === 'object') {
                this.studentTables = answers.tables || [];
                this.relations = answers.relations || [];
            }

            if (this.studentTables.length === 0) {
                this.studentTables = this.tables.map((table) => ({
                    name: table.name,
                    columns: Array.from({ length: Number(table.rows) || 0 }, () => ({ type: '', name: '' })),
                }));
            }

            this.updateAvailable();
        },

        updateAvailable() {
            const used = this.studentTables.flatMap((table) =>
                table.columns.map((column) => column.name).filter(Boolean)
            );

            this.availableAttributes = this.allAttributes.filter((attribute) => !used.includes(attribute));
        },

        addRelation() {
            this.relations.push({ from: '', to: '', type: '1:N' });
            this.saveState();
        },

        removeRelation(index) {
            this.relations.splice(index, 1);
            this.saveState();
        },

        saveState() {
            this.updateAvailable();

            this.$wire.set(`answers.${this.blockId}`, {
                tables: this.studentTables,
                relations: this.relations,
            });

            if (this.saveTimeout) clearTimeout(this.saveTimeout);

            this.saveTimeout = setTimeout(() => this.$wire.call('saveProgress'), 1000);
        },
    }));
});