<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\UserPayments;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;

    public string $search = '';
    public string $dateAsOf;
    public ?User $selectedUser = null;
    public bool $detailModal = false;

    public array $sortBy = ['column' => 'lastname', 'direction' => 'asc'];

    public function mount()
    {
        $this->dateAsOf = now()->format('Y-m-d');
    }

    #[Computed]
    public function students(): Collection
    {
        return User::query()
            ->where('role', 'student')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('firstname', 'like', "%{$this->search}%")
                      ->orWhere('lastname', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->with(['careers', 'userPayments' => function ($query) {
                $query->where('date', '<=', $this->dateAsOf);
            }])
            ->whereHas('userPayments', function ($query) {
                $query->where('date', '<=', $this->dateAsOf);
            })
            ->get()
            ->map(function ($user) {
                $user->total_debt = $user->userPayments->sum('amount') - $user->userPayments->sum('paid');
                return $user;
            })
            ->filter(fn($user) => $user->total_debt > 0)
            ->sortBy($this->sortBy['column'], SORT_REGULAR, $this->sortBy['direction'] === 'desc')
            ->values();
    }

    public function showDetail(int $userId)
    {
        $this->selectedUser = User::with(['userPayments' => function ($query) {
            $query->orderBy('date', 'desc');
        }])->find($userId);
        
        $this->detailModal = true;
    }

    #[Computed]
    public function paymentEvolution(): Collection
    {
        if (!$this->selectedUser) return collect();

        return $this->selectedUser->userPayments
            ->groupBy(fn($p) => $p->date->format('Y'))
            ->map(fn($payments, $year) => [
                'year' => $year,
                'total_amount' => $payments->sum('amount'),
                'total_paid' => $payments->sum('paid'),
                'debt' => $payments->sum('amount') - $payments->sum('paid'),
                'items' => $payments
            ])
            ->sortByDesc('year');
    }

    public function with(): array
    {
        return [
            'headers' => [
                ['key' => 'id', 'label' => 'ID', 'class' => 'w-1'],
                ['key' => 'fullname', 'label' => 'Estudiante', 'class' => 'font-semibold'],
                ['key' => 'careers_list', 'label' => 'Carrera(s)', 'sortable' => false],
                ['key' => 'total_debt', 'label' => 'Deuda Total', 'class' => 'text-right'],
                ['key' => 'actions', 'label' => '', 'sortable' => false, 'class' => 'w-1'],
            ],
            'students' => $this->students,
        ];
    }
}; ?>

<div>
    <x-header title="Reporte de Deudas" subtitle="Control de saldos pendientes de estudiantes" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Exportar" icon="o-arrow-down-tray" class="btn-primary" disabled />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="md:col-span-3">
            <x-input label="Buscar estudiante" wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" placeholder="Nombre, apellido o email..." />
        </div>
        <div>
            <x-datetime label="Deuda a la fecha" wire:model.live="dateAsOf" icon="o-calendar" type="date" />
        </div>
    </div>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$students" :sort-by="$sortBy" link="showDetail({id})">
            @scope('cell_fullname', $student)
                <div class="flex items-center gap-3">
                    <x-avatar :placeholder="substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1)" class="!w-9 !h-9" />
                    <div>
                        <div class="font-bold">{{ $student->lastname }}, {{ $student->firstname }}</div>
                        <div class="text-xs text-gray-500">{{ $student->email }}</div>
                    </div>
                </div>
            @endscope

            @scope('cell_careers_list', $student)
                <div class="flex flex-wrap gap-1">
                    @foreach($student->careers as $career)
                        <x-badge :value="$career->name" class="badge-outline badge-sm" />
                    @endforeach
                </div>
            @endscope

            @scope('cell_total_debt', $student)
                <span class="font-mono text-error font-bold text-lg">
                    $ {{ number_format($student->total_debt, 2, ',', '.') }}
                </span>
            @endscope

            @scope('actions', $student)
                <x-button icon="o-eye" wire:click="showDetail({{ $student->id }})" class="btn-ghost btn-sm" />
            @endscope
        </x-table>
        
        @if($students->isEmpty())
            <div class="text-center py-10">
                <x-icon name="o-check-circle" class="w-12 h-12 text-success mx-auto mb-2" />
                <p class="text-lg font-semibold text-gray-500">No se encontraron deudas a esta fecha.</p>
            </div>
        @endif
    </x-card>

    {{-- Detalle por estudiante --}}
    <x-modal wire:model="detailModal" title="Evolución de Pagos" separator class="backdrop-blur">
        @if($selectedUser)
            <div class="mb-4">
                <div class="text-xl font-bold">{{ $selectedUser->fullname }}</div>
                <div class="text-sm text-gray-500">Listado histórico de cuotas y saldos</div>
            </div>

            <div class="space-y-6">
                @foreach($this->paymentEvolution as $evolution)
                    <div class="border rounded-lg overflow-hidden border-base-300">
                        <div class="bg-base-200 p-3 flex justify-between items-center">
                            <span class="font-bold text-lg">Año {{ $evolution['year'] }}</span>
                            <div class="text-right">
                                <div class="text-xs uppercase text-gray-500">Deuda del año</div>
                                <div class="font-bold {{ $evolution['debt'] > 0 ? 'text-error' : 'text-success' }}">
                                    $ {{ number_format($evolution['debt'], 2, ',', '.') }}
                                </div>
                            </div>
                        </div>
                        
                        <table class="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th class="text-right">Monto</th>
                                    <th class="text-right">Pagado</th>
                                    <th class="text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($evolution['items'] as $item)
                                    <tr class="{{ $item->paid < $item->amount ? 'bg-error/5' : '' }}">
                                        <td>{{ $item->date->format('d/m/Y') }}</td>
                                        <td>{{ $item->title }}</td>
                                        <td class="text-right font-mono">$ {{ number_format($item->amount, 2, ',', '.') }}</td>
                                        <td class="text-right font-mono text-success">$ {{ number_format($item->paid, 2, ',', '.') }}</td>
                                        <td class="text-right font-mono {{ $item->paid < $item->amount ? 'text-error font-bold' : '' }}">
                                            $ {{ number_format($item->amount - $item->paid, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @endif
        
        <x-slot:actions>
            <x-button label="Cerrar" @click="$wire.detailModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
