<?php

use Livewire\Volt\Component;
use App\Models\Book;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?Book $book = null;

    // Form properties matching the database structure
    public string $title = '';
    public string $publisher = '';
    public string $author = '';
    public string $gender = '';
    public int $extent = 0;
    public ?string $edition = null;
    public ?string $isbn = null;
    public ?string $container = null;
    public ?string $signature = null;
    public ?string $digital = null;
    public string $origin = '';
    public ?string $date_added = null;
    public float $price = 0;
    public ?string $discharge_date = null;
    public ?string $discharge_reason = null;
    public string $synopsis = '';
    public ?string $note = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->book = Book::findOrFail($id);
            $this->fill($this->book->toArray());
            
            // Format dates for input type="date"
            $this->edition = $this->book->edition ? $this->book->edition->format('Y-m-d') : null;
            $this->date_added = $this->book->date_added ? $this->book->date_added->format('Y-m-d') : null;
            $this->discharge_date = $this->book->discharge_date ? $this->book->discharge_date->format('Y-m-d') : null;
        } else {
            $this->date_added = date('Y-m-d');
            $this->edition = '1973-01-01'; 
            $this->discharge_date = '1900-01-01';
            $this->origin = 'Stock';
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => 'required|max:120',
            'publisher' => 'required|max:60',
            'author' => 'required|max:60',
            'gender' => 'required|max:30',
            'extent' => 'required|integer',
            'edition' => 'required|date',
            'isbn' => 'nullable|max:20',
            'container' => 'nullable|max:40',
            'signature' => 'nullable|max:30',
            'digital' => 'nullable|max:250',
            'origin' => 'required|max:80',
            'date_added' => 'required|date',
            'price' => 'required|numeric',
            'discharge_date' => 'required|date',
            'discharge_reason' => 'nullable|max:200',
            'synopsis' => 'required',
            'note' => 'nullable|max:300',
        ]);

        if ($this->book) {
            $this->book->update($data);
            $this->success('Libro actualizado correctamente.', redirectTo: '/books');
        } else {
            Book::create($data);
            $this->success('Libro creado correctamente.', redirectTo: '/books');
        }
    }

    public function delete(): void
    {
        if (!$this->book) {
            return;
        }

        // Verificar si tiene préstamos activos antes de eliminar
        if ($this->book->loans()->where('status', 'loaned')->exists()) {
            $this->error('No se puede eliminar un libro que está actualmente prestado.');
            return;
        }

        $this->book->delete();
        $this->success('Libro eliminado correctamente.', redirectTo: '/books');
    }
}; ?>

<div>
    <x-header :title="$book ? 'Editar Libro' : 'Nuevo Libro'" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Volver" icon="o-arrow-left" link="/books" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Título" wire:model="title" placeholder="Título del libro" />
                <x-input label="Autor" wire:model="author" placeholder="Nombre del autor" />
                <x-input label="Editorial" wire:model="publisher" placeholder="Editorial" />
                <x-input label="Género" wire:model="gender" placeholder="Género literario" />
                <x-input label="ISBN" wire:model="isbn" placeholder="ISBN" />
                <x-input label="Páginas" type="number" wire:model="extent" />
                <x-input label="Fecha de Edición" type="date" wire:model="edition" />
                <x-input label="Origen" wire:model="origin" placeholder="Origen del libro" />
                <x-input label="Precio" type="number" step="0.01" wire:model="price" />
                <x-input label="Fecha de Alta" type="date" wire:model="date_added" />
                <x-input label="Contenedor" wire:model="container" />
                <x-input label="Signatura" wire:model="signature" />
                <x-input label="Enlace Digital" wire:model="digital" />
                <x-input label="Fecha de Baja" type="date" wire:model="discharge_date" />
                <x-input label="Motivo de Baja" wire:model="discharge_reason" />
                <x-input label="Notas" wire:model="note" />
            </div>
            
            <x-textarea label="Sinopsis" wire:model="synopsis" rows="5" placeholder="Resumen del libro..." />
            
            <x-slot:actions>
                @if($book)
                    <x-button label="Eliminar" icon="o-trash" class="btn-error btn-outline" 
                               wire:click="delete" wire:confirm="¿Estás seguro de eliminar este libro?" />
                @endif
                <div class="flex-1"></div>
                <x-button label="Cancelar" link="/books" />
                <x-button label="Guardar" icon="o-check" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
