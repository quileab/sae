<?php

use Livewire\Volt\Component;
use App\Models\BookLoan;
use App\Models\Book;
use App\Models\User;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public bool $overdueOnly = false; // Nuevo filtro para libros fuera de término
    public bool $showHelpModal = false;

    // Form properties (for date management)
    public string $loan_date = '';
    public ?string $return_date = null;

    public function mount(): void
    {
        $this->loan_date = date('Y-m-d');
        $this->return_date = date('Y-m-d', strtotime('+15 days'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function createLoan(): void
    {
        $book_id = (int) session('book_id', 0);
        $user_id = (int) session('user_id', 0);

        if ($book_id > 0 && $user_id > 0) {
            $book = Book::find($book_id);
            $user = User::find($user_id);

            if (!$book || !$user) {
                $this->error('El libro o el usuario marcados ya no existen.');
                return;
            }

            BookLoan::create([
                'book_id' => $book_id,
                'user_id' => $user_id,
                'loan_date' => $this->loan_date,
                'return_date' => $this->return_date,
                'notes' => 'Préstamo automático vía bookmarks.',
                'status' => 'loaned',
            ]);

            session()->forget('book_id');
            session()->forget('book_id_name');

            $this->success("Préstamo de '{$book->title}' a '{$user->name}' registrado.");
            $this->dispatch('refresh-bookmarks');
        } else {
            if ($book_id === 0 && $user_id === 0) {
                $this->warning('Debes marcar un Libro y un Usuario primero.');
            } elseif ($book_id === 0) {
                $this->warning('Falta marcar un Libro en el catálogo.');
            } else {
                $this->warning('Falta marcar un Usuario en la gestión de usuarios.');
            }
        }
    }

    public function returnBook(BookLoan $loan): void
    {
        $loan->update([
            'returned_at' => now(),
            'status' => 'returned',
        ]);

        $this->success('Libro devuelto correctamente.');
    }

    public function extendLoan(BookLoan $loan): void
    {
        $currentReturnDate = $loan->return_date ?: now();
        $newReturnDate = $currentReturnDate->copy()->isPast() ? now()->addDays(7) : $currentReturnDate->copy()->addDays(7);

        $loan->update([
            'return_date' => $newReturnDate,
            'notes' => $loan->notes . "\nPréstamo extendido el " . now()->format('d/m/Y') . ".",
        ]);

        $this->success("Préstamo extendido hasta el " . $newReturnDate->format('d/m/Y') . ".");
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'book.title', 'label' => 'Libro'],
            ['key' => 'user.name', 'label' => 'Usuario'],
            ['key' => 'loan_date', 'label' => 'Fecha Préstamo'],
            ['key' => 'return_date', 'label' => 'Fecha Estimada'],
            ['key' => 'status', 'label' => 'Estado'],
        ];
    }

    public function loans(): mixed
    {
        return BookLoan::query()
            ->with(['book', 'user'])
            ->when($this->search, function ($query) {
                $query->whereHas('book', function ($q) {
                    $q->where('title', 'like', "%{$this->search}%");
                })->orWhereHas('user', function ($q) {
                    $q->where('name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->overdueOnly, function ($query) {
                $query->where('status', 'loaned')
                      ->where('return_date', '<', now()->format('Y-m-d'));
            })
            ->latest()
            ->paginate(10);
    }

    public function with(): array
    {
        $book_id = (int) session('book_id', 0);
        $user_id = (int) session('user_id', 0);

        return [
            'loans' => $this->loans(),
            'headers' => $this->headers(),
            'isBookmarked' => ($book_id > 0 && $user_id > 0),
            'bookmarkedBook' => $book_id > 0 ? Book::find($book_id) : null,
            'bookmarkedUser' => $user_id > 0 ? User::find($user_id) : null,
        ];
    }
}; ?>

<div>
    <x-header title="Préstamos de Libros" subtitle="Gestión de entregas y devoluciones" separator progress-indicator>
        <x-slot:middle class="!justify-end flex items-center gap-4">
            <x-toggle label="Fuera de término" wire:model.live="overdueOnly" right class="toggle-error" />
            <x-input placeholder="Buscar libro o usuario..." wire:model.live.debounce="search" clear icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-question-mark-circle" class="btn-ghost btn-circle" tooltip="Ver instrucciones" @click="$wire.showHelpModal = true" />
            
            <x-button :label="$isBookmarked ? 'Confirmar Préstamo Marcado' : 'Realizar Préstamo'" 
                       :icon="$isBookmarked ? 'o-bolt' : 'o-plus'" 
                       :class="$isBookmarked ? 'btn-success' : 'btn-primary'" 
                       wire:click="createLoan" />
        </x-slot:actions>
    </x-header>

    @if($isBookmarked)
        <x-alert icon="o-bolt" class="alert-success mb-4" title="Listo para procesar">
            Confirmar préstamo de <b>{{ $bookmarkedBook->title }}</b> para <b>{{ $bookmarkedUser->name }}</b>.
        </x-alert>
    @endif

    <x-card>
        <x-table :headers="$headers" :rows="$loans" with-pagination>
            @scope('cell_loan_date', $loan)
                {{ $loan->loan_date->format('d-m-Y') }}
            @endscope

            @scope('cell_return_date', $loan)
                <span class="{{ $loan->status === 'loaned' && $loan->return_date?->isPast() ? 'text-error font-bold' : '' }}">
                    {{ $loan->return_date ? $loan->return_date->format('d-m-Y') : '-' }}
                </span>
            @endscope

            @scope('cell_status', $loan)
                <x-badge :value="$loan->status === 'loaned' ? 'Prestado' : 'Devuelto'"
                         :class="$loan->status === 'loaned' ? 'badge-warning' : 'badge-success'" />
            @endscope

            @scope('actions', $loan)
                @if($loan->status === 'loaned')
                    <x-dropdown icon="o-ellipsis-vertical" class="btn-ghost btn-sm">
                        <x-menu-item icon="o-arrow-path" label="Devolver" 
                                     wire:click="returnBook({{ $loan->id }})" />
                        <x-menu-item icon="o-calendar-days" label="Extender" 
                                     wire:click="extendLoan({{ $loan->id }})" />
                    </x-dropdown>
                @endif
            @endscope
        </x-table>
    </x-card>

    <!-- HELP MODAL -->
    <x-modal wire:model="showHelpModal" title="Instrucciones para Préstamos Rápidos" separator>
        <div class="space-y-4">
            <p>Este sistema utiliza <b>marcadores (bookmarks)</b> para agilizar el registro de préstamos:</p>
            <ol class="list-decimal ml-6 space-y-2">
                <li>Ve a la sección de <b>Usuarios</b> y marca al estudiante/usuario utilizando el icono de marcador.</li>
                <li>Ve al catálogo de <b>Libros</b> y marca el libro que deseas prestar con el icono 🔖 (amarillo).</li>
                <li>Vuelve aquí. Verás el botón en color verde y podrás confirmar el préstamo con un solo clic.</li>
            </ol>
            
            <div class="mt-4 pt-4 border-t border-base-300">
                <p class="font-bold mb-2">Estado actual:</p>
                <div class="flex gap-2">
                    @if($bookmarkedBook) 
                        <x-badge value="Libro: {{ $bookmarkedBook->title }}" class="badge-warning" />
                    @else
                        <x-badge value="Falta marcar Libro" class="badge-ghost" />
                    @endif

                    @if($bookmarkedUser)
                        <x-badge value="Usuario: {{ $bookmarkedUser->name }}" class="badge-info" />
                    @else
                        <x-badge value="Falta marcar Usuario" class="badge-ghost" />
                    @endif
                </div>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Cerrar" @click="$wire.showHelpModal = false" class="btn-primary" />
        </x-slot:actions>
    </x-modal>
</div>
