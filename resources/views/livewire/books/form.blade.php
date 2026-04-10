<?php

use Livewire\Volt\Component;
use App\Models\Book;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Http;

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

    public function fetchFromGoogle(): void
    {
        if (!$this->isbn) {
            $this->error('Por favor ingrese un ISBN.');
            return;
        }

        $response = Http::get("https://www.googleapis.com/books/v1/volumes?q=isbn:{$this->isbn}");

        if ($response->successful() && isset($response->json()['items'])) {
            $this->mapGoogleBooksData($response->json()['items'][0]);
            $this->success('Datos cargados desde Google Books.');
        } else {
            $this->error('No se encontró información en Google Books.');
        }
    }

    public function fetchFromOpenLibrary(): void
    {
        if (!$this->isbn) {
            $this->error('Por favor ingrese un ISBN.');
            return;
        }

        $isbnKey = "ISBN:{$this->isbn}";
        $response = Http::get("https://openlibrary.org/api/books?bibkeys={$isbnKey}&format=json&jscmd=data");

        if ($response->successful() && isset($response->json()[$isbnKey])) {
            $this->mapOpenLibraryData($response->json()[$isbnKey]);
            $this->success('Datos cargados desde Open Library.');
        } else {
            $this->error('No se encontró información en Open Library.');
        }
    }

    private function mapGoogleBooksData(array $item): void
    {
        $bookData = $item['volumeInfo'];
        $accessData = $item['accessInfo'] ?? [];

        $this->title = $bookData['title'] ?? $this->title;
        $this->author = isset($bookData['authors']) ? implode(', ', $bookData['authors']) : $this->author;
        $this->publisher = $bookData['publisher'] ?? $this->publisher;
        $this->extent = $bookData['pageCount'] ?? $this->extent;
        $this->synopsis = $bookData['description'] ?? $this->synopsis;
        $this->gender = isset($bookData['categories']) ? implode(', ', $bookData['categories']) : $this->gender;

        // Digital Link Selection
        $this->digital = $accessData['pdf']['downloadLink'] 
            ?? $accessData['pdf']['webReaderLink'] 
            ?? $bookData['canonicalVolumeLink'] 
            ?? $bookData['infoLink'] 
            ?? $bookData['previewLink'] 
            ?? $this->digital;

        if (isset($bookData['publishedDate'])) {
            $date = $bookData['publishedDate'];
            if (strlen($date) === 4) {
                $this->edition = "{$date}-01-01";
            } elseif (strlen($date) === 7) {
                $this->edition = "{$date}-01";
            } else {
                $this->edition = $date;
            }
        }
    }

    private function mapOpenLibraryData(array $bookData): void
    {
        $this->title = $bookData['title'] ?? $this->title;
        $this->author = isset($bookData['authors']) ? collect($bookData['authors'])->pluck('name')->implode(', ') : $this->author;
        $this->publisher = isset($bookData['publishers']) ? collect($bookData['publishers'])->pluck('name')->implode(', ') : $this->publisher;
        $this->extent = $bookData['number_of_pages'] ?? $this->extent;
        $this->synopsis = $bookData['notes'] ?? $this->synopsis;
        $this->gender = isset($bookData['subjects']) ? collect($bookData['subjects'])->pluck('name')->take(5)->implode(', ') : $this->gender;
        $this->digital = $bookData['url'] ?? $this->digital;

        if (isset($bookData['publish_date'])) {
            try {
                $this->edition = \Carbon\Carbon::parse($bookData['publish_date'])->format('Y-m-d');
            } catch (\Exception $e) {
                // If parsing fails, we keep the original or a safe default
            }
        }
    }

    public function mount(?int $id = null): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403, 'No tienes permiso para gestionar libros.');
        }

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
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403);
        }

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
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403);
        }

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
                <div class="flex items-end gap-2">
                    <x-input label="ISBN" wire:model="isbn" placeholder="ISBN" class="flex-1" />
                    <x-button label="Google" icon="o-magnifying-glass" class="btn-primary" wire:click="fetchFromGoogle" spinner="fetchFromGoogle" />
                    <x-button label="Open Library" icon="o-magnifying-glass" class="btn-secondary" wire:click="fetchFromOpenLibrary" spinner="fetchFromOpenLibrary" />
                </div>
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
