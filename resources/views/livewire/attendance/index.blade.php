<div
    x-data="{
        notes: @entangle('attendances'),
        careerId: @entangle('careerId'),
        date: @entangle('date'),
        pendingCount: 0,
        isOnline: navigator.onLine,
        isSyncing: false,

        async init() {
            this.refreshPendingCount();

            // Si estamos offline o no tenemos datos, intentamos cargar desde IndexedDB
            if (!this.isOnline || this.notes.length === 0) {
                await this.loadFromLocalCache();
            }

            // Cachear estudiantes si estamos online para futuras sesiones offline
            if (this.isOnline) {
                this.cacheStudents();
            }

            this.$watch('careerId', () => {
                if (!this.isOnline) this.loadFromLocalCache();
            });
            this.$watch('date', () => {
                if (!this.isOnline) this.loadFromLocalCache();
            });
        },

        async loadFromLocalCache() {
            if (!('indexedDB' in window)) return;
            const db = await this.openDB();
            
            // 1. Cargar estudiantes para esta careerId
            const txStudents = db.transaction('students_cache', 'readonly');
            const storeStudents = txStudents.objectStore('students_cache');
            const careerData = await this.promisify(storeStudents.get(parseInt(this.careerId)));
            
            if (careerData && careerData.students) {
                // Actualizamos la lista de estudiantes
                this.$wire.set('students', careerData.students, false);

                // Asegurar que notes tenga entradas para todos los estudiantes
                const localNotes = { ...this.notes };
                careerData.students.forEach(s => {
                    if (!localNotes[s.id]) {
                        localNotes[s.id] = { status: 'present', note: '' };
                    }
                });

                // Cargar asistencias pendientes para esta fecha y carrera
                const txPending = db.transaction('pending_attendance', 'readonly');
                const storePending = txPending.objectStore('pending_attendance');
                const allPending = await this.promisify(storePending.getAll());
                
                allPending.forEach(record => {
                    if (record.career_id == this.careerId && record.date == this.date) {
                        localNotes[record.user_id] = {
                            status: record.status,
                            note: record.note || ''
                        };
                    }
                });
                this.notes = localNotes;
            }
        },

        async cacheStudents() {
            try {
                const response = await fetch('/pwa-attendance/students', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (response.ok) {
                    const data = await response.json();
                    const db = await this.openDB();
                    const tx = db.transaction('students_cache', 'readwrite');
                    const store = tx.objectStore('students_cache');
                    await store.clear();
                    data.forEach(item => store.put(item));
                }
            } catch (e) { console.error('Error caching students', e); }
        },

        async refreshPendingCount() {
            if (!('indexedDB' in window)) return;
            const db = await this.openDB();
            const tx = db.transaction('pending_attendance', 'readonly');
            const count = await this.promisify(tx.objectStore('pending_attendance').count());
            this.pendingCount = count;
        },

        async saveAttendance() {
            if (this.isOnline) {
                // Si hay red, guardamos vía Livewire normalmente
                await $wire.save();
                // Y aprovechamos para intentar sincronizar si había algo pendiente
                this.syncPending();
            } else {
                // Si NO hay red, guardamos en IndexedDB
                const db = await this.openDB();
                const tx = db.transaction('pending_attendance', 'readwrite');
                const store = tx.objectStore('pending_attendance');
                
                // Usamos put en lugar de add. La clave compuesta [career_id, user_id, date]
                // asegura que se sobrescriba el registro si ya existía para ese día/alumno.
                for (const [userId, data] of Object.entries(this.notes)) {
                    await store.put({
                        career_id: parseInt(this.careerId),
                        user_id: parseInt(userId),
                        date: this.date,
                        status: data.status,
                        note: data.note || ''
                    });
                }
                
                await this.refreshPendingCount();
                $wire.dispatch('toast', { type: 'info', message: 'Guardado localmente. Se sincronizará al recuperar la conexión.' });
            }
        },

        async syncPending() {
            if (!('indexedDB' in window) || this.isSyncing || !this.isOnline) return;
            
            const db = await this.openDB();
            const tx = db.transaction('pending_attendance', 'readonly');
            const all = await this.promisify(tx.objectStore('pending_attendance').getAll());
            
            if (all.length === 0) return;

            this.isSyncing = true;
            try {
                const response = await fetch('/pwa-attendance/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ records: all }),
                });
                
                if (response.ok) {
                    const tx2 = db.transaction('pending_attendance', 'readwrite');
                    tx2.objectStore('pending_attendance').clear();
                    await this.refreshPendingCount();
                    $wire.dispatch('toast', { type: 'success', message: 'Sincronización completada.' });
                }
            } catch (e) { console.error('Sync failed', e); }
            finally { this.isSyncing = false; }
        },

        openDB() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open('sae_attendance', 3);
                req.onupgradeneeded = e => {
                    const db = e.target.result;
                    if (db.objectStoreNames.contains('pending_attendance')) {
                        db.deleteObjectStore('pending_attendance');
                    }
                    db.createObjectStore('pending_attendance', { keyPath: ['career_id', 'user_id', 'date'] });
                    
                    if (!db.objectStoreNames.contains('students_cache')) {
                        db.createObjectStore('students_cache', { keyPath: 'career_id' });
                    }
                };
                req.onsuccess = e => resolve(e.target.result);
                req.onerror = e => reject(e);
            });
        },

        promisify(req) {
            return new Promise((resolve, reject) => {
                req.onsuccess = e => resolve(e.target.result);
                req.onerror = e => reject(e);
            });
        },
    }"
    @online.window="isOnline = true; syncPending()"
    @offline.window="isOnline = false"
>
    {{-- HEADER --}}
    <div class="sticky top-0 z-40 bg-base-100/90 backdrop-blur-md shadow-sm border-b border-base-200">
        
        {{-- ESTADOS DE CONEXIÓN Y SINCRONIZACIÓN (Integrados) --}}
        <div x-show="!isOnline" class="bg-amber-500 text-white text-[10px] uppercase tracking-wider font-bold py-1 px-4 flex items-center gap-2 border-b border-amber-600 shadow-inner" x-cloak>
            <x-icon name="o-signal-slash" class="w-4 h-4" />
            <span>Sin conexión — Cambios pendientes</span>
            <span x-show="pendingCount > 0" class="badge badge-ghost badge-xs ml-auto border-white/20 text-white" x-text="pendingCount + ' cambios'"></span>
        </div>

        <div x-show="isOnline && pendingCount > 0" class="bg-info text-info-content text-[10px] uppercase tracking-wider font-bold py-1 px-4 flex items-center gap-2 border-b border-info-content/10 shadow-inner" x-cloak>
            <x-icon name="o-arrow-path" class="w-3 h-3" x-bind:class="isSyncing ? 'animate-spin' : ''" />
            <span x-text="isSyncing ? 'Sincronizando...' : pendingCount + ' registros pendientes'"></span>
            <button x-show="!isSyncing" @click="syncPending()" class="btn btn-link btn-xs p-0 h-auto min-h-0 ml-auto no-underline text-white font-bold text-[10px]">Sincronizar ahora</button>
        </div>

        <div class="p-3 px-4">
            <div class="flex items-center justify-between gap-2 max-w-4xl mx-auto">
                <div class="flex gap-2 items-center flex-1">
                    <x-button icon="o-arrow-left" class="btn-ghost btn-sm" @click="window.history.back()" />
                    <span class="font-bold text-lg hidden sm:inline">Asistencia</span>
                    
                    {{-- Selector de División --}}
                    <select wire:model.live="careerId" class="select select-bordered select-sm flex-1 sm:max-w-[200px]">
                        @foreach($careers as $career)
                            <option value="{{ $career->id }}">{{ $career->name }}</option>
                        @endforeach
                    </select>
                    {{-- Selector de Fecha --}}
                    <input type="date" wire:model.live="date" class="input input-bordered input-sm w-36" />
                </div>

                <x-button
                    icon="o-check"
                    label=""
                    class="btn-success btn-sm text-white btn-square"
                    @click="saveAttendance()"
                    wire:loading.attr="disabled"
                    x-bind:disabled="isSyncing"
                />
            </div>
        </div>
    </div>

    {{-- LISTA DE ALUMNOS --}}
    <div class="px-2 pb-24 space-y-2 max-w-4xl mx-auto mt-4">
        @forelse($students as $student)
            @php
                $uid = $student['id'];
                $status = $attendances[$uid]['status'] ?? 'present';
                $total = $totals[$uid] ?? 0;
                $alertLevel = match(true) {
                    $total >= 10 => 'error',
                    $total >= 5  => 'warning',
                    $total >= 2  => 'info',
                    default      => null,
                };
            @endphp
            <div class="card bg-base-100 shadow-sm border border-base-200 transition-all" 
                 wire:key="student-{{ $uid }}"
                 x-data="{ open: false }">
                <div class="card-body p-0 gap-0">

                    {{-- Header: Siempre visible --}}
                    <div class="flex items-center justify-between gap-2 p-3 cursor-pointer hover:bg-base-200/50 transition-colors" @click="open = !open">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono opacity-50">{{ $loop->iteration }}.</span>
                            <span class="font-semibold text-base leading-tight">
                                {{ $student['name'] ?? ($student['lastname'] . ', ' . $student['firstname']) }}
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            {{-- Resumen del estado actual (visible cuando está cerrado) --}}
                            <template x-if="!open">
                                <div class="flex items-center gap-1">
                                    <span class="badge badge-sm" 
                                          :class="{
                                              'badge-success': notes[{{ $uid }}].status === 'present',
                                              'badge-warning': notes[{{ $uid }}].status === 'late',
                                              'badge-info': notes[{{ $uid }}].status === 'early_leave',
                                              'badge-secondary': notes[{{ $uid }}].status === 'half_absent',
                                              'badge-error': notes[{{ $uid }}].status === 'absent'
                                          }">
                                        <span x-text="
                                            notes[{{ $uid }}].status === 'present' ? '✅' :
                                            notes[{{ $uid }}].status === 'late' ? '⏰' :
                                            notes[{{ $uid }}].status === 'early_leave' ? '🚪' :
                                            notes[{{ $uid }}].status === 'half_absent' ? '½' : '❌'
                                        "></span>
                                    </span>
                                    <template x-if="notes[{{ $uid }}].note">
                                        <x-icon name="o-chat-bubble-bottom-center-text" class="w-3 h-3 opacity-50" />
                                    </template>
                                </div>
                            </template>

                            @if($alertLevel)
                                <div class="tooltip" data-tip="Total: {{ $total }} inasistencias">
                                    <span class="badge badge-{{ $alertLevel }} badge-sm gap-1">
                                        <x-icon name="o-exclamation-triangle" class="w-3 h-3" />
                                        {{ $total }}
                                    </span>
                                </div>
                            @endif
                            
                            <x-icon name="o-chevron-down" class="w-4 h-4 opacity-50 transition-transform" ::class="open ? 'rotate-180' : ''" />
                        </div>
                    </div>

                    {{-- Contenido Desplegable --}}
                    <div x-show="open" x-collapse class="px-3 pb-3 pt-0 space-y-3 border-t border-base-200">
                        <div class="pt-3">
                            {{-- Botones de estado --}}
                            <div class="grid grid-cols-5 gap-1 text-xs">
                                @foreach([
                                    'present'     => ['label' => 'Presente',  'emoji' => '✅', 'class' => 'btn-success'],
                                    'late'        => ['label' => 'Tarde',     'emoji' => '⏰', 'class' => 'btn-warning'],
                                    'early_leave' => ['label' => 'Retiro',    'emoji' => '🚪', 'class' => 'btn-info'],
                                    'half_absent' => ['label' => 'Media F.',  'emoji' => '½',  'class' => 'btn-secondary'],
                                    'absent'      => ['label' => 'Ausente',   'emoji' => '❌', 'class' => 'btn-error'],
                                ] as $key => $opt)
                                    <button
                                        type="button"
                                        @click="notes[{{ $uid }}].status = '{{ $key }}'"
                                        class="btn btn-xs"
                                        :class="notes[{{ $uid }}].status === '{{ $key }}' ? '{{ $opt['class'] }}' : 'btn-ghost border border-base-300'"
                                    >
                                        <span class="text-base leading-none">{{ $opt['emoji'] }}</span>
                                        <span class="hidden sm:inline">{{ $opt['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- Nota --}}
                            <div class="mt-3">
                                <input
                                    type="text"
                                    maxlength="100"
                                    placeholder="Nota (opcional)..."
                                    x-model="notes[{{ $uid }}].note"
                                    class="input input-bordered input-xs w-full"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-base-content/50 py-12">
                <x-icon name="o-users" class="w-10 h-10 mx-auto mb-2" />
                <p>No hay alumnos en esta división.</p>
            </div>
        @endforelse
    </div>

    {{-- BOTÓN GUARDAR FLOTANTE (móvil) --}}
    <div class="fixed bottom-4 right-4 z-50 md:hidden">
        <x-button
            icon="o-check"
            label=""
            class="btn-success btn-circle btn-lg shadow-lg text-white"
            @click="saveAttendance()"
            x-bind:disabled="isSyncing"
        />
    </div>
</div>
