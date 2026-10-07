/**
 * Relational schema design widget for evaluation table_design blocks.
 *
 * Reproduces the diagram the source exercises were drawn as: a band of tables,
 * then the connectors pointing at the band below. The student fills fixed slots
 * (they cannot add rows), marks each cell as [  ] / [PK] / [FK], and picks the
 * cardinality of each declared connector.
 *
 * The attribute pool legitimately repeats tokens — several tables share a
 * generic "ID" primary key — so availability is computed per table rather than
 * globally deduplicated.
 *
 * Registered in the bundle rather than pushed through @push('scripts'): a pushed
 * <script> is re-inserted on every Livewire morph, which re-runs the
 * registration after Alpine has already booted.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('tableDesign', (config) => ({
        blockId: config.blockId,
        attributes: (config.attributes || []).filter(Boolean),
        tables: config.tables || [],
        pairs: config.pairs || [],
        relationTypes: config.relationTypes || ['1:1', '1:N', 'N:N'],

        studentTables: [],
        relations: [],

        saveTimeout: null,

        init() {
            const saved = this.$wire.get(`answers.${this.blockId}`);

            this.studentTables = this.tables.map((table) => ({
                name: table.name,
                columns: this.blankColumns(table.rows),
            }));

            if (saved && typeof saved === 'object') {
                (saved.tables || []).forEach((savedTable) => {
                    const target = this.studentTables.find((t) => t.name === savedTable.name);
                    if (!target) return;
                    target.columns = (savedTable.columns || []).map((column) => ({
                        type: column.type || '',
                        name: column.name || '',
                    }));
                });

                this.relations = this.pairs.map((pair) => {
                    const match = (saved.relations || []).find((r) => r.clave === pair.clave);
                    return { clave: pair.clave, type: match?.type || '' };
                });
            } else {
                this.syncRelations();
            }
        },

        blankColumns(rows) {
            return Array.from({ length: Number(rows) || 0 }, () => ({ type: '', name: '' }));
        },

        /**
         * One entry per declared connector, so the student chooses a cardinality
         * for a fixed pair instead of inventing an origin and a target.
         */
        syncRelations() {
            this.relations = this.pairs.map((pair) => {
                const match = this.relations.find((r) => r.clave === pair.clave);
                return { clave: pair.clave, type: match?.type || '' };
            });
        },

        columnsFor(name) {
            return this.studentTables.find((table) => table.name === name)?.columns ?? [];
        },

        slotsFor(name) {
            return this.columnsFor(name).length;
        },

        /**
         * Attributes not placed yet in any other table, plus whatever this row
         * already holds so the select can keep showing its own value.
         */
        /**
         * Options for one cell: the unique attributes still available, plus
         * whatever this cell already holds so the select keeps its value.
         *
         * Availability is global, not per table: with five "ID" tokens, placing
         * one leaves four, so every table can still be given a primary key.
         */
        optionsFor(current) {
            const options = Array.from(new Set(this.remainingAttributes()));
            const value = (current || '').trim();

            if (value && !options.includes(value)) options.push(value);

            return options.sort((a, b) => a.localeCompare(b));
        },

        /**
         * Attributes still available, as a multiset.
         *
         * The pool repeats tokens on purpose: five tables share a generic "ID"
         * primary key, so there must be five "ID" tokens. Removing one occurrence
         * per placement is what makes the remaining four still assignable to the
         * other tables; collapsing the pool into a set would delete all five the
         * moment one is placed.
         */
        remainingAttributes() {
            const pool = [...this.attributes];

            this.studentTables.forEach((table) => {
                table.columns.forEach((column) => {
                    const name = (column.name || '').trim();
                    if (!name) return;

                    const position = pool.indexOf(name);
                    if (position > -1) pool.splice(position, 1);
                });
            });

            return pool;
        },

        emptyCells() {
            return this.studentTables.reduce(
                (total, table) => total + table.columns.filter((column) => !column.name).length,
                0
            );
        },

        relationLabel(clave) {
            const pair = this.pairs.find((p) => p.clave === clave);
            if (!pair) return clave;
            return `${this.displayName(pair.from)} → ${this.displayName(pair.to)}`;
        },

        displayName(key) {
            return String(key || '').replace(/_/g, ' ').toUpperCase();
        },

        saveState() {
            this.$wire.set(`answers.${this.blockId}`, {
                tables: this.studentTables.map((table) => ({
                    name: table.name,
                    columns: table.columns.map((column) => ({
                        type: column.type || '',
                        name: column.name || '',
                    })),
                })),
                relations: this.relations.map((relation) => ({
                    clave: relation.clave,
                    type: relation.type || '',
                })),
            });

            if (this.saveTimeout) clearTimeout(this.saveTimeout);

            this.saveTimeout = setTimeout(() => this.$wire.call('saveProgress'), 1000);
        },
    }));
});