<?php

use Livewire\Volt\Component;
use App\Models\Book;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public bool $searchByGender = false;
    public bool $searchBySynopsis = false;

    public array $sortBy = ['column' => 'title', 'direction' => 'asc'];

    public function clear(): void
    {
        $this->reset(['search', 'searchByGender', 'searchBySynopsis']);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function bookmark(Book $book): void
    {
        $this->dispatch('bookmarked', [
            'type' => 'book_id',
            'value' => $book->id
        ]);

        $this->success("Libro '{$book->title}' marcado para préstamo.");
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'title', 'label' => 'Título', 'sortable' => true],
            ['key' => 'author', 'label' => 'Autor', 'sortable' => true],
            ['key' => 'publisher', 'label' => 'Editorial'],
            ['key' => 'gender', 'label' => 'Género'],
            ['key' => 'signature', 'label' => 'Signatura'],
        ];
    }

    public function books(): mixed
    {
        return Book::query()
            ->with(['loans' => function ($query) {
                $query->where('status', 'loaned')->with('user');
            }])
            ->withExists(['loans as is_loaned' => function ($query) {
                $query->where('status', 'loaned');
            }])
            ->when($this->search, function ($query) {
                $words = collect(explode(' ', $this->search))->filter()->all();
                $fields = [];
                if ($this->searchByGender) $fields[] = 'gender';
                if ($this->searchBySynopsis) $fields[] = 'synopsis';
                if (empty($fields)) $fields = ['title', 'author', 'signature'];

                $query->where(function($q) use ($words, $fields) {
                    foreach ($words as $word) {
                        $q->where(function($sub) use ($word, $fields) {
                            foreach ($fields as $field) {
                                $sub->orWhere($field, 'like', "%{$word}%");
                            }
                        });
                    }
                });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(10);
    }

    public function with(): array
    {
        $placeholder = "Buscar título, autor o signatura...";
        if ($this->searchByGender && $this->searchBySynopsis) {
            $placeholder = "Buscar en género y sinopsis...";
        } elseif ($this->searchByGender) {
            $placeholder = "Buscar por género...";
        } elseif ($this->searchBySynopsis) {
            $placeholder = "Buscar en sinopsis...";
        }

        return [
            'books' => $this->books(),
            'headers' => $this->headers(),
            'placeholder' => $placeholder,
        ];
    }
}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Libros" subtitle="Gestión del catálogo de la biblioteca" separator progress-indicator>
        <x-slot:middle class="!justify-end flex items-center gap-4">
            <div class="flex gap-4 items-center">
                <x-checkbox label="Género" wire:model.live="searchByGender" class="checkbox-primary checkbox-sm" />
                <x-checkbox label="Sinopsis" wire:model.live="searchBySynopsis" class="checkbox-primary checkbox-sm" />
            </div>
            
            <x-input 
                :placeholder="$placeholder" 
                wire:model.live.debounce="search" 
                clear 
                icon="o-magnifying-glass" 
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button 
                label="Imprimir" 
                icon="o-printer" 
                class="btn-outline" 
                :link="'/books/print?search=' . $search . '&gender=' . ($searchByGender ? '1' : '0') . '&synopsis=' . ($searchBySynopsis ? '1' : '0')" 
                external
                target="_blank"
            />
            <x-button label="Nuevo Libro" icon="o-plus" class="btn-primary" link="/books/create" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE -->
    <x-card>
        <x-table 
            :headers="$headers" 
            :rows="$books" 
            :sort-by="$sortBy" 
            with-pagination
            link="/books/{id}/edit"
            :row-decoration="['bg-warning/10 text-warning font-semibold' => fn($book) => $book->is_loaned]"
        >
            
            @scope('cell_title', $book)
                <div class="flex items-center gap-2">
                    {{ $book->title }}
                    @if($book->is_loaned)
                        <x-badge value="PRESTADO" class="badge-warning badge-xs" />
                    @endif
                </div>
            @endscope

            @scope('actions', $book)
                <div class="flex gap-2">
                    @if($book->is_loaned)
                        @php $activeLoan = $book->loans->first(); @endphp
                        @if($activeLoan && $activeLoan->user)
                            <x-button 
                                icon="o-question-mark-circle" 
                                class="btn-ghost btn-sm text-warning" 
                                tooltip="{{ $activeLoan->user->full_name }}" 
                                link="/user/{{ $activeLoan->user->id }}"
                                @click.stop=""
                            />
                        @endif
                    @else
                        <x-button 
                            icon="o-bookmark" 
                            wire:click="bookmark({{ $book->id }})" 
                            class="btn-ghost btn-sm text-yellow-500" 
                            tooltip="Marcar para préstamo" 
                            @click.stop=""
                        />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
