<?php

use Livewire\Volt\Component;
use App\Models\UserPayments;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $cycleYear = null;
    public $inscriptionsStatus = null;
    public $subjects = [];
    public $subject_id;
    public ?UserPayments $nextPayment = null;
    public $mp_key = null;
    public $mp_token = null;
    public $showCareerWarning = false;
    public $usersWithoutCareerCount = 0;

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

        // Check if user has no career (only for students)
        if (Auth::user()->hasRole('student')) {
            if (Auth::user()->careers()->count() == 0) {
                $this->showCareerWarning = true;
            }
        } else {
            // For elevated permissions, count students without career
            $this->usersWithoutCareerCount = \App\Models\User::where('role', 'student')->doesntHave('careers')->count();
        }

        // if session cycle is set, use it
        if (session()->has('cycle_id')) {
            $this->cycleYear = session('cycle_id');
        } else {
            // if not, set it to the current year
            $this->cycleYear = date('Y');
            session()->put('cycle_id', $this->cycleYear);
            session()->put('cycle_name', $this->cycleYear);
        }

        $this->inscriptionsStatus = \App\Models\Configs::where('group', 'inscriptions')->get();
        $this->subjects = \App\Models\User::find(Auth::user()->id)->subjects()->with('career')->get();
        if (!$this->subjects->isEmpty()) {
            $this->subject_id = $this->subjects->first()->id;
        }

        if (Auth::user()->hasRole('student')) {
            $this->nextPayment = UserPayments::where('user_id', Auth::id())
                ->whereRaw('paid < amount')
                ->orderBy('date', 'asc')
                ->first();
        }
    }

    public function saveCycleYear(): void
    {
        // session()->put('cycle', $this->cycleYear);
        // session()->put('cycle_name', $this->cycleYear);
        $id = $this->cycleYear;
        // emit change
        $this->dispatch('bookmarked', ['type' => 'cycle_id', 'value' => $id]);
    }
}; ?>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @if($showCareerWarning)
        <x-alert title="Advertencia: Usuario sin carrera asociada. Informe a la Institución" icon="o-exclamation-triangle"
            class="alert-warning md:col-span-2 mb-4" />
    @endif
    <x-card title="{{ config('app.name') }}" shadow-md class="bg-base-200">
        {{-- Select Cycle Year --}}
        <x-input label="Ciclo lectivo" wire:model="cycleYear" icon="o-calendar" type="number" min="2023" max="2030"
            step="1">
            <x-slot:append>
                {{-- Add `rounded-s-none` class (RTL support) --}}
                <x-button label="Cambiar" icon="o-check" class="btn-primary rounded-r" wire:click="saveCycleYear" />
            </x-slot:append>
        </x-input>

        <div class="grid grid-cols-1 gap-2 mt-4 md:grid-cols-2">
            @foreach ($inscriptionsStatus as $inscription)
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
                    value="{{ $usersWithoutCareerCount }}" 
                    icon="o-user-minus" 
                    class="text-warning"
                />
            @endif
        </div>
    </x-card>
    <x-card title="Opciones Rápidas" shadow-md class="bg-base-200">
        LIBROS DE TEMAS
        <x-select label="Materias" wire:model.live="subject_id" :options="$subjects" option-label="full_name"
            option-value="id" icon="o-queue-list" />
        <div class="flex items-center mt-1 space-x-2">
            <x-button label="LIBRO" icon="o-book-open" class="btn-primary" wire:model="subject_id"
                link="/printClassbooks/{{ $subject_id }}/{{ Auth::user()->id }}" external no-wire-navigate />
            <x-button label="CONTENIDO" icon="o-document-text" class="btn-primary"
                link="/simplified-content/{{ $subject_id }}" no-wire-navigate />
        </div>
    </x-card>

    @if(auth()->user()->hasRole('student') && $nextPayment)
        <x-card title="Próximo Pago Pendiente" shadow-md>
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold">{{ $nextPayment->title }}</h3>
                    <p class="text-sm">Vencimiento: {{ \Carbon\Carbon::parse($nextPayment->date)->format('d/m/Y') }}</p>
                    <p class="text-xl font-bold mt-2">Monto:
                        ${{ number_format($nextPayment->amount - $nextPayment->paid, 2) }}</p>
                </div>
                <div>
                    <livewire:online-payment :userPaymentId="$nextPayment->id" />
                </div>
            </div>
        </x-card>
    @endif

    <div class="md:col-span-2">
        <livewire:upcoming-exams />
    </div>
    <div class="w-full text-xs text-primary">
        fwk:{{ app()->version() }}/{{ phpversion() }}/{{ env('APP_ENV') }}/{{ env('APP_DEBUG') == 1 ? 'Debug' : 'Release' }}
    </div>

</div>