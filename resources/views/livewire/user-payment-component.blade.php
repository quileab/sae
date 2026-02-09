@php
    $isAdmin = auth()->user()->hasAnyRole(['admin', 'principal', 'administrative']);
    $isStudent = auth()->user()->hasRole('student');
@endphp

<div>
    @if($plansError)
        <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4">
            {{ __('Error: No se pudo cargar la configuración de planes de pago (Tabla \'plans_masters\' no encontrada).') }}
        </x-alert>
    @endif

    @if ($user)
        {{-- Modals --}}
        <x-modal wire:model="openModal" title="{{ $user->full_name }}" subtitle="ID: {{ $userId }}" separator>
            <div class="flex items-center justify-evenly gap-4">
                <x-select wire:model.live="selectedPlan" label="{{ __('Seleccionar Plan') }}" :options="$this->payPlans" option-value="id" option-label="title" />
                <x-checkbox wire:model="combinePlans" label="{{ __('Combinar con otros planes') }}" class="mt-6" />
            </div>
            <x-slot:actions>
                <x-button label="{{ __('Salir') }}" @click="$wire.openModal = false" class="btn-secondary" />
                <x-button label="{{ __('Asignar Plan') }}" wire:click="assignPayPlan" class="btn-primary" spinner="assignPayPlan" />
            </x-slot:actions>
        </x-modal>

        <x-modal wire:model="paymentModal" title="{{ $user->full_name }}" subtitle="ID: {{ $userId }}" separator>
            <div class="space-y-4">
                <p>{{ __('Pagando:') }} <span class="font-bold">{{ $paymentDescription }}</span> » {{ __('Valor:') }} <span class="font-bold">$ {{ number_format($paymentAmountPaid, 2) }}</span></p>
                @if($isAdmin)
                    <x-input type="number" wire:model="paymentAmountInput" label="{{ __('Monto a ingresar') }}" prefix="$" />
                @endif
            </div>
            <x-slot:actions>
                @if($isAdmin)
                    <x-button label="{{ __('Ingresar Pago') }}" wire:click="registerUserPayment" class="btn-success" spinner="registerUserPayment" />
                @endif
                <x-button label="{{ __('Salir') }}" @click="$wire.paymentModal = false" class="btn-secondary" />
            </x-slot:actions>
        </x-modal>

        <x-modal wire:model="modifyPaymentModal" title="{{ __('ID de Pago:') }} {{ $paymentId }} » {{ $user->full_name }}" separator>
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex-1 text-sm text-gray-500">
                    {{ __('Modificar monto a pagar:') }} <span class="font-bold">{{ $paymentDescription }}</span><br />
                    {{ __('A Pagar:') }} <span class="font-bold">$ {{ number_format($paymentAmountPaid, 2) }}</span>
                </div>
                <x-input type="number" wire:model="totalDebt" class="w-full md:w-32" />
                <x-button label="{{ __('Modificar') }}" wire:click="modifyAmount({{$paymentId}})" class="btn-primary" spinner="modifyAmount" />
            </div>
            <x-slot:actions>
                <x-button label="{{ __('Salir') }}" @click="$wire.modifyPaymentModal = false" class="btn-secondary" />
            </x-slot:actions>
        </x-modal>

        {{-- Header & Search --}}
        <div class="flex flex-col lg:flex-row items-center justify-between gap-4 px-4 py-4 bg-base-200 rounded-lg shadow-sm mb-6">
            <div class="flex items-center gap-4 w-full lg:w-auto">
                @if($isAdmin)
                    <x-button icon="o-magnifying-glass" wire:click="$set('userId', null); $set('user', null)" class="btn-ghost btn-circle" tooltip="{{ __('Nueva búsqueda') }}" />
                @endif
                <div>
                    <h1 class="text-xl font-bold leading-tight">
                        {{ $user->full_name }}
                        <span class="text-primary text-sm font-normal ml-1"># {{ $user->id }}</span>
                    </h1>
                    @if($user->careers->isNotEmpty())
                        <div class="text-xs text-gray-500">{{ $user->careers->pluck('name')->implode(', ') }}</div>
                    @endif
                </div>
            </div>

            @if($isAdmin)
                <div class="w-full lg:w-80">
                    <livewire:students.search />
                </div>
            @endif

            @if($isStudent && $this->nextPaymentToPay)
                <div class="flex items-center gap-4 p-2 bg-base-100 rounded-lg shadow-inner">
                    <div class="text-right leading-tight text-sm">
                        <div class="font-semibold">{{ $this->nextPaymentToPay->title }}</div>
                        <div class="text-primary font-bold">$ {{ number_format($this->nextPaymentToPay->amount - $this->nextPaymentToPay->paid, 2) }}</div>
                    </div>
                    <livewire:online-payment :userPaymentId="$this->nextPaymentToPay->id" />
                </div>
            @endif

            <div class="flex gap-2">
                @if($isAdmin)
                    @if ($this->userPayments->isNotEmpty())
                        @if ($this->totals['paid'] < $this->totals['debt'])
                            <x-button wire:click="addPaymentToUser" icon="o-currency-dollar" label="{{ __('Ingresar Pago') }}" class="btn-success btn-sm md:btn-md" spinner="addPaymentToUser" />
                        @endif
                        <x-button icon="o-list-bullet" label="{{ __('Ver Pagos') }}" link="{{ route('payments-details', $user->id) }}" class="btn-primary btn-sm md:btn-md" />
                    @endif
                    <x-button wire:click="$set('openModal',true)" icon="o-plus-circle" label="{{ __('Agregar Plan') }}" class="btn-primary btn-sm md:btn-md" />
                @endif
            </div>
        </div>

        {{-- Notifications --}}
        @foreach(['success', 'error', 'info'] as $type)
            @if (session($type))
                <x-alert icon="o-information-circle" class="alert-{{ $type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info') }} mb-4" dismissible>
                    {{ session($type) }}
                    @if($type === 'success') - {{ __('El pago puede tardar unos minutos en actualizarse.') }} @endif
                </x-alert>
            @endif
        @endforeach

        {{-- Payment Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3 px-2 mb-8">
            @foreach ($this->userPayments as $userPayment)
                @php
                    $isClickable = $isAdmin;
                    $cardClasses = "relative overflow-hidden text-sm uppercase bg-gray-700 rounded-lg shadow hover:shadow-md transition-all duration-200 border-t-4 " . $userPayment->borderColor;
                @endphp

                @if($isClickable)
                    <button wire:click="handleInstallmentClick({{$userPayment->id}})" wire:key="payment-{{ $userPayment->id }}" class="{{ $cardClasses }} hover:brightness-110 active:scale-95 text-left w-full">
                        <div class="p-2 border-b border-gray-600/50 bg-gray-800/30">
                            <div class="font-bold truncate text-gray-200">{{ $userPayment->title }}</div>
                            <div class="text-[10px] text-gray-400 font-medium">{{ $userPayment->date->format('d/m/Y') }}</div>
                        </div>
                        <div class="p-2 text-right">
                            <div class="text-base font-mono text-white">$ {{ number_format($userPayment->paid, 2) }}</div>
                            <div class="{{$userPayment->textColor}} text-[10px] font-bold" title="{{ __('Importe total') }}">$ {{ number_format($userPayment->amount, 2) }}</div>
                        </div>
                    </button>
                @else
                    <div wire:key="payment-{{ $userPayment->id }}" class="{{ $cardClasses }}">
                        <div class="p-2 border-b border-gray-600/50 bg-gray-800/30">
                            <div class="font-bold truncate text-gray-200">{{ $userPayment->title }}</div>
                            <div class="text-[10px] text-gray-400 font-medium">{{ $userPayment->date->format('d/m/Y') }}</div>
                        </div>
                        <div class="p-2 text-right">
                            <div class="text-base font-mono text-white">$ {{ number_format($userPayment->paid, 2) }}</div>
                            <div class="{{$userPayment->textColor}} text-[10px] font-bold">$ {{ number_format($userPayment->amount, 2) }}</div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Totals --}}
        <div class="max-w-md ml-auto px-4 py-4 bg-base-200 rounded-xl shadow-inner text-right space-y-1">
            <div class="flex justify-between items-center text-gray-500">
                <span>{{ __('Deuda Total') }}</span>
                <span class="font-mono text-lg">$ {{ number_format($this->totals['debt'], 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center text-success">
                <span>{{ __('Total Pagado') }}</span>
                <span class="font-mono text-lg font-bold">$ {{ number_format($this->totals['paid'], 2, ',', '.') }}</span>
            </div>
            <div class="pt-2 border-t border-gray-300 dark:border-gray-600 flex justify-between items-center text-xl font-bold">
                <span>{{ __('Saldo Pendiente') }}</span>
                <span class="text-primary font-mono">$ {{ number_format($this->totals['debt'] - $this->totals['paid'], 2, ',', '.') }}</span>
            </div>
        </div>
    @else
        {{-- Search State --}}
        <div class="max-w-2xl mx-auto mt-16 px-4">
            @if($userId)
                <x-alert icon="o-exclamation-triangle" title="{{ __('Estudiante no encontrado') }}" class="alert-error mb-6 shadow-lg" dismissible>
                    {{ __('No se ha podido encontrar un estudiante con el ID :id.', ['id' => $userId]) }}
                </x-alert>
            @endif

            <x-card class="bg-base-100 shadow-xl border border-base-300" separator>
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-primary/10 text-primary rounded-full">
                            <x-icon name="o-currency-dollar" class="w-8 h-8" />
                        </div>
                        <div>
                            <div class="text-2xl font-black">{{ __('Control de Pagos') }}</div>
                            <div class="text-sm font-normal opacity-70">{{ __('Administración centralizada de cuentas') }}</div>
                        </div>
                    </div>
                </x-slot:title>

                <div class="py-8">
                    <div class="mb-4 text-center text-sm font-semibold opacity-60 uppercase tracking-widest">{{ __('Buscar Estudiante') }}</div>
                    <livewire:students.search />
                </div>
                
                <x-slot:actions>
                    <div class="flex justify-center w-full gap-4 border-t border-base-200 pt-4">
                        <x-button label="{{ __('Reporte General') }}" link="{{ route('report-payments') }}" icon="o-document-chart-bar" class="btn-ghost btn-sm" />
                        <x-button label="{{ __('Ayuda') }}" icon="o-question-mark-circle" class="btn-ghost btn-sm" />
                    </div>
                </x-slot:actions>
            </x-card>
        </div>
    @endif

    <div x-data="{}" @open-receipt.window="window.open($event.detail.url, '_blank')"></div>
</div>