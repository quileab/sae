<div class="grid grid-cols-1 gap-4 md:grid-cols-2">

    @if($showCareerWarning)
        <x-alert title="Advertencia: Usuario sin carrera asociada. Informe a la Institución" icon="o-exclamation-triangle"
            class="alert-warning md:col-span-2 mb-4" />
    @endif

    {{-- Panel de Ciclo Lectivo --}}
    <x-card title="{{ config('app.name') }}" shadow-md class="bg-primary/5 border-t-4 border-t-primary">
        {{-- Select Cycle Year --}}
        <x-input label="Ciclo lectivo" wire:model.live="cycle_id" icon="o-calendar" type="number" min="2023" max="2030"
            step="1">
            <x-slot:append>
                <x-button label="Cambiar" icon="o-check" class="btn-primary rounded-r" wire:click="saveCycleYear" />
            </x-slot:append>
        </x-input>

        @island(lazy: true)
            <div class="grid grid-cols-1 gap-2 mt-4 md:grid-cols-2">
                @foreach ($this->inscriptionsStatus as $inscription)
                    <div wire:key="inscription-{{ $inscription['id'] ?? $loop->index }}">
                        <x-stat title="{{ $inscription['description'] }}"
                            value="{{ $inscription['value'] == 'true' ? 'Habilitadas' : 'Sin Fecha' }}"
                            icon="o-clipboard-document-check"
                            class="{{ $inscription['value'] == 'true' ? 'text-success' : 'text-gray-400' }}" />
                    </div>
                @endforeach


            </div>
            @placeholder
                <div class="grid grid-cols-1 gap-2 mt-4 md:grid-cols-2 w-full">
                    <div class="skeleton h-24 w-full bg-base-300"></div>
                    <div class="skeleton h-24 w-full bg-base-300"></div>
                </div>
            @endplaceholder
        @endisland
    </x-card>

    {{-- Panel de Mis Materias --}}
    <x-card title="Mis Materias" shadow-md class="bg-warning/5 border-t-4 border-t-warning">
        <div class="mb-2 font-semibold text-warning flex items-center gap-2">
            <x-icon name="o-book-open" class="w-4 h-4" />
            GESTIÓN DE CLASES
        </div>
        <x-select label="Seleccionar Materia" wire:model.live="subject_id" :options="$this->subjects"
            option-label="full_name" option-value="id" icon="o-queue-list" />
        <div class="grid grid-cols-3 gap-2 mt-4">
            <x-button label="LIBRO TEMAS" icon="o-book-open" class="btn-warning btn-soft btn-sm w-full"
                link="/printClassbooks/{{ $subject_id }}/{{ Auth::user()->id }}?cycle={{ $this->cycleYear }}" external
                no-wire-navigate :disabled="!$subject_id" />
            <x-button label="CONTENIDO" icon="o-document-text" class="btn-warning btn-soft btn-sm w-full"
                link="/subjects-content/{{ $subject_id }}" no-wire-navigate :disabled="!$subject_id" />
            <x-button label="CHAT" icon="o-chat-bubble-left-right" class="btn-warning btn-soft btn-sm w-full"
                link="/chat?subject_id={{ $subject_id }}" no-wire-navigate :disabled="!$subject_id" />
        </div>
    </x-card>

    @if(auth()->user()->isStaff())
        {{-- Panel de Estadísticas de Estudiantes --}}
        <x-card title="Estadísticas de Estudiantes" subtitle="Información general de alumnos activos" shadow-md class="bg-base-100 border-t-4 border-t-primary md:col-span-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div class="flex flex-col items-center justify-center p-3.5 rounded-lg bg-base-200 border border-base-300 text-center">
                    <x-icon name="o-users" class="w-6 h-6 text-base-content/70 mb-1" />
                    <div class="font-semibold text-sm">Total Activos</div>
                    <span class="text-2xl font-bold">{{ $this->studentStats['total'] }}</span>
                </div>

                <div class="flex flex-col items-center justify-center p-3.5 rounded-lg bg-success/10 border border-success/20 text-center">
                    <x-icon name="o-check-circle" class="w-6 h-6 text-success mb-1" />
                    <div class="font-semibold text-success text-sm">Habilitados</div>
                    <span class="text-2xl font-bold text-success">{{ $this->studentStats['enabled'] }}</span>
                </div>

                <div class="flex flex-col items-center justify-center p-3.5 rounded-lg bg-error/10 border border-error/20 text-center">
                    <x-icon name="o-no-symbol" class="w-6 h-6 text-error mb-1" />
                    <div class="font-semibold text-error text-sm">Deshabilitados</div>
                    <span class="text-2xl font-bold text-error">{{ $this->studentStats['disabled'] }}</span>
                </div>

                <div class="flex flex-col items-center justify-center p-3.5 rounded-lg bg-info/10 border border-info/20 text-center">
                    <x-icon name="o-calendar-days" class="w-6 h-6 text-info mb-1" />
                    <div class="font-semibold text-info text-sm">Asistieron (Mes)</div>
                    <span class="text-2xl font-bold text-info">{{ $this->studentStats['with_attendance'] }}</span>
                </div>

                <div class="flex flex-col items-center justify-center p-3.5 rounded-lg bg-warning/10 border border-warning/20 text-center">
                    <x-icon name="o-user-minus" class="w-6 h-6 text-warning mb-1" />
                    <div class="font-semibold text-warning text-sm">Sin Carrera</div>
                    <span class="text-2xl font-bold text-warning">{{ $this->studentStats['without_career'] }}</span>
                </div>
            </div>
        </x-card>
    @endif

    @if(auth()->user()->hasRole('student') && $this->nextPayment)
        <x-card title="Próximo Pago Pendiente" shadow-md class="bg-warning/5 border-t-4 border-t-warning">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold">{{ $this->nextPayment->title }}</h3>
                    <p class="text-sm">Vencimiento: {{ \Carbon\Carbon::parse($this->nextPayment->date)->format('d/m/Y') }}
                    </p>
                    <p class="text-xl font-bold mt-2 text-warning">Monto:
                        ${{ number_format($this->nextPayment->amount - $this->nextPayment->paid, 2) }}</p>
                </div>
                <div>
                    <livewire:online-payment :userPaymentId="$this->nextPayment->id" />
                </div>
            </div>
        </x-card>
    @endif

    <div class="md:col-span-2">
        <livewire:upcoming-exams />
    </div>

    @if(auth()->check() && auth()->user()->hasRole('admin') && config('app.debug'))
        <div class="w-full text-xs text-primary opacity-50 mt-4">
            fwk:{{ app()->version() }}/{{ phpversion() }}/{{ config('app.env') }}
        </div>
    @endif

</div>