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

        <div class="grid grid-cols-1 gap-2 mt-4 md:grid-cols-2">
            @foreach ($this->inscriptionsStatus as $inscription)
                <x-stat
                    title="{{ $inscription['description'] }}"
                    value="{{ $inscription['value'] == 'true' ? 'Habilitadas' : 'Sin Fecha' }}"
                    icon="o-clipboard-document-check"
                    class="{{ $inscription['value'] == 'true' ? 'text-success' : 'text-gray-400' }}"
                />
            @endforeach

            @if(!auth()->user()->hasRole('student'))
                <x-stat 
                    title="Sin carrera" 
                    description="Estudiantes sin inscripción"
                    value="{{ $this->usersWithoutCareerCount }}" 
                    icon="o-user-minus" 
                    class="text-warning"
                />
            @endif
        </div>
    </x-card>

    {{-- Panel de Mis Materias --}}
    <x-card title="Mis Materias" shadow-md class="bg-orange-500/5 border-t-4 border-t-orange-500">
        <div class="mb-2 font-semibold text-orange-600 flex items-center gap-2">
            <x-icon name="o-book-open" class="w-4 h-4" />
            GESTIÓN DE CLASES
        </div>
        <x-select label="Seleccionar Materia" wire:model.live="subject_id" :options="$this->subjects" option-label="full_name"
            option-value="id" icon="o-queue-list" />
        <div class="grid grid-cols-2 gap-2 mt-4">
            <x-button label="VER LIBRO" icon="o-book-open" class="btn-warning btn-sm text-white w-full" 
                link="/printClassbooks/{{ $subject_id }}/{{ Auth::user()->id }}?cycle={{ $this->cycleYear }}" 
                external no-wire-navigate />
            <x-button label="CONTENIDO" icon="o-document-text" class="btn-outline border-orange-500 text-orange-600 hover:bg-orange-500 hover:text-white btn-sm w-full"
                link="/subjects-content/{{ $subject_id }}" no-wire-navigate />
        </div>
    </x-card>

    @if(auth()->user()->hasRole('student') && $this->nextPayment)
        <x-card title="Próximo Pago Pendiente" shadow-md class="bg-warning/5 border-t-4 border-t-warning">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold">{{ $this->nextPayment->title }}</h3>
                    <p class="text-sm">Vencimiento: {{ \Carbon\Carbon::parse($this->nextPayment->date)->format('d/m/Y') }}</p>
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
    
    <div class="w-full text-xs text-primary opacity-50 mt-4">
        fwk:{{ app()->version() }}/{{ phpversion() }}/{{ env('APP_ENV') }}/{{ env('APP_DEBUG') == 1 ? 'Debug' : 'Release' }}
    </div>

</div>