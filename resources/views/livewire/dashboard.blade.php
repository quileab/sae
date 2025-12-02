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

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

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
    <x-card title="{{ config('app.name') }}" shadow-md>
        {{-- Select Cycle Year --}}
        <x-input label="Ciclo lectivo" wire:model="cycleYear" icon="o-calendar" type="number" min="2023" max="2030"
            step="1">
            <x-slot:append>
                {{-- Add `rounded-s-none` class (RTL support) --}}
                <x-button label="Guardar" icon="o-check" class="btn-primary rounded-s-none"
                    wire:click="saveCycleYear" />
            </x-slot:append>
        </x-input>

        @foreach ($inscriptionsStatus as $inscription)
            <div
                class="mt-2 grid grid-cols-1 border border-primary p-2 rounded-md
                                                                                                            {{ $inscription['value'] == 'true' ? 'bg-success/10' : 'bg-error/10' }}">
                <x-icon name="{{ $inscription['value'] == 'true' ? 'o-check' : 'o-x-mark' }}"
                    class="{{ $inscription['value'] == 'true' ? 'text-success' : 'text-error' }}"
                    label="{{ $inscription['description'] }}" />
            </div>

        @endforeach
    </x-card>
    <x-card title="Opciones Rápidas"
        subtitle="fwk:{{ app()->version() }}/{{ phpversion() }}/{{ env('APP_ENV') }}/{{ env('APP_DEBUG') == 1 ? 'Debug' : 'Release' }}"
        shadow-md>
        LIBROS DE TEMAS
        <x-select label="Materias" wire:model.live="subject_id" :options="$subjects" option-label="full_name"
            option-value="id" icon="o-queue-list" />
        <div class="flex items-center justify-between mt-1 space-x-2">
            <x-button label="VER LIBRO" icon="o-document-text" class="btn-primary" wire:model="subject_id"
                link="/printClassbooks/{{ $subject_id }}/{{ Auth::user()->id }}" external no-wire-navigate />
            <x-button label="VER CONTENIDO" icon="o-eye" class="btn-secondary"
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
</div>