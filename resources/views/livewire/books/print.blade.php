<?php

use Livewire\Volt\Component;
use App\Models\Book;
use Livewire\Attributes\Layout;

new #[Layout('layouts.empty')] class extends Component {
    public string $search = '';
    public bool $gender = false;
    public bool $synopsis = false;

    public function mount(): void
    {
        $this->search = request()->query('search', '');
        $this->gender = request()->boolean('gender');
        $this->synopsis = request()->boolean('synopsis');
    }

    public function books(): mixed
    {
        return Book::query()
            ->when($this->search, function ($query) {
                $words = collect(explode(' ', $this->search))->filter()->all();
                $fields = [];
                if ($this->gender) $fields[] = 'gender';
                if ($this->synopsis) $fields[] = 'synopsis';
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
            ->orderBy('title', 'asc')
            ->get();
    }

    public function with(): array
    {
        return [
            'books' => $this->books(),
        ];
    }
}; ?>

<div class="p-8 bg-white text-black min-h-screen">
    <div class="flex justify-between items-center border-b-2 border-black pb-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold uppercase">Catálogo de Libros</h1>
            <p class="text-sm italic">Generado el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        <div class="text-right text-sm">
            @if($search)
                <p><b>Filtro:</b> "{{ $search }}"</p>
                <p><b>En:</b> {{ $gender ? 'Género' : ($synopsis ? 'Sinopsis' : 'Título/Autor/Signatura') }}</p>
            @endif
            <p><b>Total:</b> {{ count($books) }} libros</p>
        </div>
    </div>

    <table class="w-full text-sm border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 p-2 text-left w-12">#</th>
                <th class="border border-gray-300 p-2 text-left">Título</th>
                <th class="border border-gray-300 p-2 text-left">Autor</th>
                <th class="border border-gray-300 p-2 text-left">Editorial</th>
                <th class="border border-gray-300 p-2 text-left">Género</th>
                <th class="border border-gray-300 p-2 text-left w-32">Signatura</th>
            </tr>
        </thead>
        <tbody>
            @foreach($books as $book)
                <tr class="even:bg-gray-50">
                    <td class="border border-gray-300 p-2">{{ $book->id }}</td>
                    <td class="border border-gray-300 p-2 font-bold">{{ $book->title }}</td>
                    <td class="border border-gray-300 p-2">{{ $book->author }}</td>
                    <td class="border border-gray-300 p-2">{{ $book->publisher }}</td>
                    <td class="border border-gray-300 p-2">{{ $book->gender }}</td>
                    <td class="border border-gray-300 p-2 font-mono">{{ $book->signature }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-8 text-center text-xs text-gray-400 no-print">
        <x-button label="Imprimir Documento" icon="o-printer" class="btn-primary" onclick="window.print()" />
    </div>

    <style>
        @page {
            margin: 1cm;
        }
        @media print {
            .no-print { display: none; }
            body { background: white; margin: 0; padding: 0; }
        }
    </style>
</div>
