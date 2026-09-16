<?php

use Livewire\Volt\Component;
use App\Models\Book;
use App\Services\BookSignatureService;
use App\Services\CduClassificationService;
use App\Services\IsbnLookupService;
use App\Services\BookGenderService;
use App\Services\BookContainerService;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    public ?Book $book = null;

    // Wizard Step state (1 to 4)
    public int $currentStep = 1;

    // Step 1: General Data & Work Type
    public string $work_type = 'general';
    public string $title = '';
    public ?string $author = '';
    public ?string $publisher = '';
    public ?string $isbn = '';
    public ?string $edition = null;
    public string $synopsis = '';

    // Step 2: Classification & Subject
    public string $cdu = '82-3';
    public string $gender = '';
    public array $selectedGenres = [];
    public string $customGenreInput = '';
    public string $genreSearchQuery = '';
    public string $cduSearchQuery = '';
    public array $cduSearchResults = [];

    // Step 3: Collection & Exemplars
    public string $collection_code = '000';
    public string $collection_name = '';
    public int $copies_count = 1;
    public float $price = 0;
    public string $origin = 'Stock';
    public ?string $date_added = null;
    public ?string $container = '';
    public int $extent = 0;
    public ?string $note = null;
    public ?string $digital = null;
    public ?string $signature = null;
    public ?string $discharge_date = null;
    public ?string $discharge_reason = null;

    // Step 4: Preview items
    public array $generatedExemplars = [];

    // Online Search
    public string $bookSearchQuery = '';
    public array $bookSearchResults = [];

    public function mount(?int $id = null): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403, 'No tienes permiso para gestionar libros.');
        }

        /** @var BookGenderService $genderService */
        $genderService = app(BookGenderService::class);

        if ($id) {
            $this->book = Book::findOrFail($id);
            $this->fill($this->book->toArray());

            $this->selectedGenres = $genderService->toArray($this->gender);
            $this->edition = $this->book->edition ? $this->book->edition->format('Y-m-d') : null;
            $this->date_added = $this->book->date_added ? $this->book->date_added->format('Y-m-d') : null;
            $this->discharge_date = $this->book->discharge_date ? $this->book->discharge_date->format('Y-m-d') : null;

            // Intentar inferir work_type desde la signatura si existe
            if ($this->signature && str_contains($this->signature, '-')) {
                $parts = explode('-', $this->signature);
                $sec = $parts[0] ?? 'GEN';
                foreach (BookSignatureService::WORK_TYPES as $wtKey => $wtInfo) {
                    if ($wtInfo['code'] === $sec) {
                        $this->work_type = $wtKey;
                        break;
                    }
                }
                if (isset($parts[1])) {
                    $this->cdu = $parts[1];
                }
                if (isset($parts[3])) {
                    $this->collection_code = $parts[3];
                }
            }
        } else {
            $this->date_added = date('Y-m-d');
            $this->edition = date('Y') . '-01-01';
            $this->discharge_date = '1900-01-01';
        }

        $this->searchCdu();
    }

    public function toggleGenre(string $name): void
    {
        $name = trim($name);
        if (empty($name)) return;

        if (in_array($name, $this->selectedGenres)) {
            $this->selectedGenres = array_values(array_filter($this->selectedGenres, fn($g) => $g !== $name));
        } else {
            $this->selectedGenres[] = $name;
        }

        $this->updateGenderString();
    }

    public function addCustomGenre(): void
    {
        $name = trim($this->customGenreInput);
        if (!empty($name) && !in_array($name, $this->selectedGenres)) {
            $this->selectedGenres[] = $name;
            $this->customGenreInput = '';
            $this->updateGenderString();
        }
    }

    public function removeGenre(int $index): void
    {
        if (isset($this->selectedGenres[$index])) {
            array_splice($this->selectedGenres, $index, 1);
            $this->updateGenderString();
        }
    }

    protected function updateGenderString(): void
    {
        /** @var BookGenderService $genderService */
        $genderService = app(BookGenderService::class);
        $this->gender = $genderService->toString($this->selectedGenres);
    }

    public function updatedWorkType(string $value): void
    {
        /** @var CduClassificationService $cduService */
        $cduService = app(CduClassificationService::class);
        $this->cdu = $cduService->suggestByWorkType($value);
    }

    public function searchCdu(): void
    {
        /** @var CduClassificationService $cduService */
        $cduService = app(CduClassificationService::class);
        $this->cduSearchResults = $cduService->search($this->cduSearchQuery);
    }

    public function updatedCduSearchQuery(): void
    {
        $this->searchCdu();
    }

    public function selectCdu(string $code): void
    {
        $this->cdu = $code;
        $this->success("CDU {$code} seleccionada.");
    }

    public function searchOnlineBooks(): void
    {
        if (strlen(trim($this->bookSearchQuery)) < 3) {
            $this->error('Ingrese al menos 3 caracteres para buscar.');
            return;
        }

        /** @var IsbnLookupService $isbnService */
        $isbnService = app(IsbnLookupService::class);
        $this->bookSearchResults = $isbnService->searchByTitleOrAuthor($this->bookSearchQuery);

        if (empty($this->bookSearchResults)) {
            $this->warning('No se encontraron resultados en los catálogos en línea.');
        } else {
            $this->info('Se encontraron ' . count($this->bookSearchResults) . ' coincidencias.');
        }
    }

    public function selectOnlineBook(int $index): void
    {
        if (!isset($this->bookSearchResults[$index])) {
            return;
        }

        $book = $this->bookSearchResults[$index];

        $this->title = $book['title'] ?? $this->title;
        $this->author = $book['author'] ?? $this->author;
        $this->publisher = $book['publisher'] ?? $this->publisher;
        $this->isbn = !empty($book['isbn']) ? $book['isbn'] : $this->isbn;
        $this->edition = !empty($book['edition']) ? $book['edition'] : $this->edition;
        $this->synopsis = !empty($book['synopsis']) ? $book['synopsis'] : $this->synopsis;
        $this->gender = !empty($book['gender']) ? $book['gender'] : $this->gender;

        /** @var BookGenderService $genderService */
        $genderService = app(BookGenderService::class);
        $this->selectedGenres = $genderService->toArray($this->gender);
        $this->extent = !empty($book['extent']) ? (int) $book['extent'] : $this->extent;
        $this->digital = !empty($book['digital']) ? $book['digital'] : $this->digital;

        $this->bookSearchResults = [];
        $this->bookSearchQuery = '';

        $this->success("Datos autocompletados desde {$book['source']}.");
    }

    public function fetchIsbnData(): void
    {
        if (!$this->isbn) {
            $this->error('Por favor ingrese un número de ISBN.');
            return;
        }

        /** @var IsbnLookupService $isbnService */
        $isbnService = app(IsbnLookupService::class);
        $result = $isbnService->lookup($this->isbn);

        if ($result['success']) {
            $data = $result['data'];
            $this->title = !empty($data['title']) ? $data['title'] : $this->title;
            $this->author = !empty($data['author']) ? $data['author'] : $this->author;
            $this->publisher = !empty($data['publisher']) ? $data['publisher'] : $this->publisher;
            $this->extent = !empty($data['extent']) ? (int) $data['extent'] : $this->extent;
            $this->synopsis = !empty($data['synopsis']) ? $data['synopsis'] : $this->synopsis;
            $this->gender = !empty($data['gender']) ? $data['gender'] : $this->gender;

            /** @var BookGenderService $genderService */
            $genderService = app(BookGenderService::class);
            $this->selectedGenres = $genderService->toArray($this->gender);
            $this->digital = !empty($data['digital']) ? $data['digital'] : $this->digital;

            if (!empty($data['edition'])) {
                $this->edition = $data['edition'];
            }

            $this->success("{$result['message']} (Fuente: {$result['source']})");
        } else {
            $this->error($result['message']);
        }
    }

    public function nextStep(): void
    {
        $this->validateCurrentStep();

        if ($this->currentStep < 4) {
            $this->currentStep++;
            if ($this->currentStep === 4) {
                $this->buildPreview();
            }
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        } elseif ($step > $this->currentStep) {
            for ($i = $this->currentStep; $i < $step; $i++) {
                $this->currentStep = $i;
                $this->validateCurrentStep();
            }
            $this->currentStep = $step;
            if ($this->currentStep === 4) {
                $this->buildPreview();
            }
        }
    }

    protected function validateCurrentStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'work_type' => 'required|in:general,dictionary,atlas,children,juvenile,teacher,periodical,media',
                'title' => 'required|max:120',
                'author' => 'required|max:60',
                'publisher' => 'required|max:60',
                'isbn' => 'nullable|max:25',
                'edition' => 'nullable|date',
                'synopsis' => 'nullable',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'cdu' => 'required|max:20',
                'gender' => 'required|max:200',
            ]);
        } elseif ($this->currentStep === 3) {
            $this->validate([
                'collection_code' => 'nullable|max:10',
                'copies_count' => 'required|integer|min:1|max:50',
                'origin' => 'required|max:80',
                'price' => 'required|numeric|min:0',
                'date_added' => 'required|date',
                'container' => 'nullable|max:60',
                'extent' => 'nullable|integer',
            ]);
        }
    }

    public function getSpineLabelProperty(): array
    {
        /** @var BookSignatureService $service */
        $service = app(BookSignatureService::class);
        return $service->getSpineLabelData(
            $this->work_type,
            $this->cdu,
            $this->author,
            $this->title,
            $this->collection_code,
            1
        );
    }

    public function getBaseSignatureProperty(): string
    {
        /** @var BookSignatureService $service */
        $service = app(BookSignatureService::class);
        return $service->generateSignature(
            $this->work_type,
            $this->cdu,
            $this->author,
            $this->title,
            $this->collection_code,
            1
        );
    }

    public function buildPreview(): void
    {
        /** @var BookSignatureService $service */
        $service = app(BookSignatureService::class);
        $this->generatedExemplars = [];

        $count = $this->book ? 1 : $this->copies_count;

        for ($i = 1; $i <= $count; $i++) {
            $sig = $service->generateSignature(
                $this->work_type,
                $this->cdu,
                $this->author,
                $this->title,
                $this->collection_code,
                $i
            );

            $this->generatedExemplars[] = [
                'exemplar_number' => $i,
                'exemplar_code' => $service->formatExemplar($i),
                'signature' => $sig,
            ];
        }
    }

    public function save(): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403);
        }

        $this->currentStep = 1; $this->validateCurrentStep();
        $this->currentStep = 2; $this->validateCurrentStep();
        $this->currentStep = 3; $this->validateCurrentStep();
        $this->currentStep = 4;

        /** @var BookSignatureService $service */
        $service = app(BookSignatureService::class);

        if ($this->book) {
            // Edición de libro individual existente
            $signature = $service->generateSignature(
                $this->work_type,
                $this->cdu,
                $this->author,
                $this->title,
                $this->collection_code,
                1
            );

            $this->book->update([
                'title' => $this->title,
                'author' => $this->author,
                'publisher' => $this->publisher,
                'gender' => $this->gender,
                'extent' => $this->extent ?: 0,
                'edition' => $this->edition ?: null,
                'isbn' => $this->isbn,
                'container' => $this->container,
                'signature' => $signature,
                'digital' => $this->digital,
                'origin' => $this->origin,
                'date_added' => $this->date_added,
                'price' => $this->price,
                'discharge_date' => $this->discharge_date,
                'discharge_reason' => $this->discharge_reason,
                'synopsis' => $this->synopsis ?: 'Sin descripción',
                'note' => $this->note,
            ]);

            $this->success("Libro '{$this->title}' actualizado correctamente.", redirectTo: '/books');
        } else {
            // Creación en lote de nuevo(s) libro(s)
            DB::transaction(function () use ($service) {
                for ($i = 1; $i <= $this->copies_count; $i++) {
                    $signature = $service->generateSignature(
                        $this->work_type,
                        $this->cdu,
                        $this->author,
                        $this->title,
                        $this->collection_code,
                        $i
                    );

                    Book::create([
                        'title' => $this->title,
                        'author' => $this->author,
                        'publisher' => $this->publisher,
                        'gender' => $this->gender,
                        'extent' => $this->extent ?: 0,
                        'edition' => $this->edition ?: null,
                        'isbn' => $this->isbn,
                        'container' => $this->container,
                        'signature' => $signature,
                        'digital' => $this->digital,
                        'origin' => $this->origin,
                        'date_added' => $this->date_added,
                        'price' => $this->price,
                        'synopsis' => $this->synopsis ?: 'Sin descripción',
                        'note' => $this->note,
                        'user_id' => auth()->id(),
                    ]);
                }
            });

            $count = $this->copies_count;
            $this->success("Se han catalogado correctamente {$count} ejemplar(es) con sus signaturas topográficas.", redirectTo: '/books');
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

        // Verificar préstamos activos
        if ($this->book->loans()->where('status', 'loaned')->exists()) {
            $this->error('No se puede eliminar un libro que está actualmente prestado.');
            return;
        }

        $this->book->delete();
        $this->success('Libro eliminado correctamente.', redirectTo: '/books');
    }
}; ?>

<div>
    <x-header 
        :title="$book ? 'Editar Catalogación del Libro' : 'Asistente de Catalogación Escolar (Smart Key)'" 
        :subtitle="$book ? 'Modifique los datos bibliográficos y la signatura topográfica' : 'Catalogación guiada paso a paso con búsqueda de ISBN en cascada y sugerencias CDU'" 
        separator 
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="Volver a Libros" icon="o-arrow-left" link="/books" />
        </x-slot:actions>
    </x-header>

    <!-- Wizard Step Indicator Nav -->
    <div class="mb-6">
        <ul class="steps steps-vertical lg:steps-horizontal w-full bg-base-100 p-4 rounded-xl shadow-xs border border-base-200">
            <li class="step {{ $currentStep >= 1 ? 'step-primary' : '' }} cursor-pointer" wire:click="goToStep(1)">
                <span class="font-medium text-sm">1. Datos Generales y Tipo</span>
            </li>
            <li class="step {{ $currentStep >= 2 ? 'step-primary' : '' }} cursor-pointer" wire:click="goToStep(2)">
                <span class="font-medium text-sm">2. Buscador CDU y Materia</span>
            </li>
            <li class="step {{ $currentStep >= 3 ? 'step-primary' : '' }} cursor-pointer" wire:click="goToStep(3)">
                <span class="font-medium text-sm">3. Colección y Ejemplares</span>
            </li>
            <li class="step {{ $currentStep >= 4 ? 'step-primary' : '' }} cursor-pointer" wire:click="goToStep(4)">
                <span class="font-medium text-sm">4. Resumen y Tejuelo</span>
            </li>
        </ul>
    </div>

    <!-- Step 1: General Data -->
    @if($currentStep === 1)
        <x-card title="Paso 1: Datos Generales y Tipo de Obra Escolar" subtitle="Seleccione la categoría de la obra o busque en línea por Título/Autor/ISBN para autocompletar automáticamente.">
            <!-- Online Title/Author/ISBN Search Bar -->
            <div class="mb-6 p-4 bg-primary/10 rounded-xl border border-primary/20">
                <label class="font-bold text-sm text-primary flex items-center gap-2 mb-2">
                    <x-icon name="o-magnifying-glass" class="w-5 h-5 text-primary" />
                    Buscador de Libros en Línea (Por Título, Autor o ISBN)
                </label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <x-input 
                        wire:model.live.debounce.400ms="bookSearchQuery" 
                        placeholder="Ej: Cien Años de Soledad, Gabriel García Márquez o 9789500700153..." 
                        class="flex-1"
                        icon="o-book-open"
                        wire:keydown.enter="searchOnlineBooks"
                    />
                    <div class="flex gap-2">
                        <x-button label="Buscar Título/Autor" icon="o-sparkles" class="btn-primary" wire:click="searchOnlineBooks" spinner="searchOnlineBooks" />
                        <x-button label="Buscar por ISBN" icon="o-qr-code" class="btn-secondary" wire:click="fetchIsbnData" spinner="fetchIsbnData" />
                    </div>
                </div>

                @if(!empty($bookSearchResults))
                    <div class="mt-3 bg-base-100 rounded-lg border border-base-300 divide-y divide-base-200 shadow-lg max-h-64 overflow-y-auto">
                        <div class="p-2 bg-base-200 text-xs font-bold text-base-content/70 flex justify-between">
                            <span>Resultados encontrados (Haga clic para autocompletar)</span>
                            <span>Fuente</span>
                        </div>
                        @foreach($bookSearchResults as $index => $bookItem)
                            <div wire:click="selectOnlineBook({{ $index }})" 
                                 class="p-3 hover:bg-primary/10 cursor-pointer flex items-start justify-between transition-colors">
                                <div class="space-y-1">
                                    <p class="font-bold text-sm text-base-content">{{ $bookItem['title'] }}</p>
                                    <p class="text-xs text-base-content/70">
                                        <span class="font-semibold">Autor:</span> {{ $bookItem['author'] ?: 'Desconocido' }} | 
                                        <span class="font-semibold">Editorial:</span> {{ $bookItem['publisher'] ?: 'S/D' }} | 
                                        <span class="font-semibold">ISBN:</span> {{ $bookItem['isbn'] ?: 'S/N' }}
                                    </p>
                                </div>
                                <x-badge :value="$bookItem['source']" class="badge-neutral text-xs" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <x-select 
                        label="Tipo de Obra Escolar (Define la Sección de 3 letras en el Tejuelo)" 
                        wire:model.live="work_type" 
                        :options="[
                            ['id' => 'general', 'name' => 'Fondo General / Ensayo / Ciencia (GEN)'],
                            ['id' => 'dictionary', 'name' => 'Diccionario / Enciclopedia / Consulta (REF)'],
                            ['id' => 'atlas', 'name' => 'Atlas / Mapas / Geografía (ATL)'],
                            ['id' => 'children', 'name' => 'Literatura Infantil / Primeros Lectores (INF)'],
                            ['id' => 'juvenile', 'name' => 'Literatura Juvenil / Narrativa 12-18 años (JUV)'],
                            ['id' => 'teacher', 'name' => 'Documentación Docente / Manuales de Profesorado (DOC)'],
                            ['id' => 'periodical', 'name' => 'Revistas / Periódicos / Historietas / Cómics (REV)'],
                            ['id' => 'media', 'name' => 'Material Didáctico / Audiovisual / Kits / Láminas (MED)'],
                        ]"
                        option-value="id"
                        option-label="name"
                        icon="o-bookmark"
                        class="select-primary font-medium"
                    />
                </div>

                <x-input label="Título del Libro *" wire:model.live="title" placeholder="Ej: Atlas Geográfico de la República Argentina" icon="o-book-open" />
                <x-input label="Autor(es) *" wire:model.live="author" placeholder="Ej: Instituto Geográfico Nacional" icon="o-user" />
                <x-input label="Editorial *" wire:model="publisher" placeholder="Ej: Kapelusz / Estrada / Santillana" />
                <x-input label="ISBN" wire:model="isbn" placeholder="Ej: 9789500700153" icon="o-qr-code" />

                <!-- 3 Columns row: Páginas, Fecha de Edición, Origen -->
                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-input label="Páginas / Extensión" type="number" min="0" wire:model="extent" icon="o-document" placeholder="Ej: 240" />
                    <x-input label="Fecha de Edición" type="date" wire:model="edition" icon="o-calendar" />
                    <x-input label="Origen / Procedencia *" wire:model="origin" placeholder="Ej: Stock, Compra, Donación" icon="o-building-storefront" />
                </div>

                <div class="md:col-span-2">
                    <x-textarea label="Sinopsis / Reseña Bibliográfica" wire:model="synopsis" rows="8" placeholder="Resumen del contenido escolar..." />
                </div>
            </div>

            <x-slot:actions>
                <div class="flex justify-between w-full">
                    <x-button label="Cancelar" link="/books" />
                    <x-button label="Siguiente Paso" icon-right="o-arrow-right" class="btn-primary" wire:click="nextStep" />
                </div>
            </x-slot:actions>
        </x-card>
    @endif

    <!-- Step 2: CDU & Subject -->
    @if($currentStep === 2)
        <x-card title="Paso 2: Buscador Integrado de Clasificación Decimal Universal (CDU)" subtitle="Seleccione o busque por materia para obtener la CDU oficial normalizada.">
            <div class="space-y-6">
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <x-input 
                            label="Buscar en Catálogo Escolar CDU (Por nombre, área o código)" 
                            wire:model.live.debounce.300ms="cduSearchQuery" 
                            placeholder="Ej: atlas, matemática, historia, cuento..." 
                            icon="o-magnifying-glass"
                            clear
                        />

                        <div class="mt-3 max-h-60 overflow-y-auto border border-base-200 rounded-xl divide-y divide-base-200">
                            @forelse($cduSearchResults as $cduItem)
                                <div wire:click="selectCdu('{{ $cduItem['code'] }}')" 
                                     class="p-3 hover:bg-base-200 cursor-pointer flex items-center justify-between transition-colors {{ $cdu === $cduItem['code'] ? 'bg-primary/10 border-l-4 border-primary font-bold' : '' }}">
                                    <div>
                                        <span class="badge badge-neutral font-mono mr-2">{{ $cduItem['code'] }}</span>
                                        <span class="text-sm text-base-content">{{ $cduItem['name'] }}</span>
                                    </div>
                                    <x-icon name="o-check-circle" class="w-5 h-5 {{ $cdu === $cduItem['code'] ? 'text-primary' : 'text-base-300' }}" />
                                </div>
                            @empty
                                <div class="p-4 text-center text-sm text-base-content/60">
                                    No se encontraron materias que coincidan. Puede ingresar el código CDU manualmente al lado.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="space-y-4">
                        <x-input label="CDU Seleccionada / Normalizada *" wire:model.live="cdu" placeholder="Ej: 912 o 82-3" icon="o-hashtag" hint="Formato estándar numérico" />
                        
                        <!-- Multi-Genre Tagging Section -->
                        <div class="p-4 bg-base-200/70 rounded-xl border border-base-300 space-y-3">
                            <label class="font-bold text-sm text-base-content flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <x-icon name="o-tag" class="w-4 h-4 text-primary" />
                                    Géneros / Materias / Etiquetas Pedagógicas *
                                </span>
                                <span class="text-xs font-normal text-base-content/60">{{ count($selectedGenres) }} seleccionada(s)</span>
                            </label>

                            <!-- Active Selected Tags -->
                            <div class="flex flex-wrap gap-2 min-h-10 p-2 bg-base-100 rounded-lg border border-base-200 items-center">
                                @forelse($selectedGenres as $idx => $genreName)
                                    <span class="badge badge-primary gap-1 p-3 text-xs font-bold shadow-xs">
                                        {{ $genreName }}
                                        <button type="button" wire:click="removeGenre({{ $idx }})" class="hover:text-error ml-1 font-bold">×</button>
                                    </span>
                                @empty
                                    <span class="text-xs text-base-content/50 italic">Ningún género seleccionado. Seleccione de la lista o escriba uno abajo.</span>
                                @endforelse
                            </div>

                            <!-- Custom Genre Add Input -->
                            <div class="flex items-center gap-2">
                                <x-input 
                                    wire:model="customGenreInput" 
                                    placeholder="Agregar género personalizado (ej: Novela Gráfica)..." 
                                    class="input-sm flex-1"
                                    wire:keydown.enter.prevent="addCustomGenre"
                                />
                                <x-button label="Agregar" icon="o-plus" class="btn-primary btn-sm" wire:click="addCustomGenre" type="button" />
                            </div>

                            <!-- Fast Suggestion Chips -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-base-content/70">Sugerencias frecuentes (Haga clic para agregar/quitar):</span>
                                    <x-input wire:model.live.debounce.200ms="genreSearchQuery" placeholder="Filtrar..." class="input-xs w-36" />
                                </div>
                                
                                @php
                                    $genderService = app(BookGenderService::class);
                                    $suggestions = $genderService->filterSuggestions($genreSearchQuery);
                                @endphp

                                <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1">
                                    @foreach($suggestions as $sug)
                                        @php $isSelected = in_array($sug, $selectedGenres); @endphp
                                        <button 
                                            type="button" 
                                            wire:click="toggleGenre('{{ $sug }}')"
                                            class="badge text-xs p-2.5 transition-all cursor-pointer {{ $isSelected ? 'badge-primary font-bold' : 'badge-outline hover:badge-primary' }}">
                                            {{ $isSelected ? '✓ ' : '+ ' }}{{ $sug }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Cutter Box -->
                        <div class="p-4 bg-base-200 rounded-xl border border-base-300">
                            <div class="flex items-center gap-3">
                                <x-icon name="o-sparkles" class="w-6 h-6 text-primary" />
                                <div>
                                    <p class="text-xs font-semibold text-base-content/70 uppercase tracking-wider">Código Cutter Calculado (3 Letras)</p>
                                    <p class="text-lg font-bold text-primary">{{ $this->spineLabel['cutter'] }} <span class="text-xs text-base-content/60 font-normal">(Generado desde: "{{ $author ?: $title }}")</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <x-slot:actions>
                <div class="flex justify-between w-full">
                    <x-button label="Atrás" icon="o-arrow-left" wire:click="previousStep" />
                    <x-button label="Siguiente Paso" icon-right="o-arrow-right" class="btn-primary" wire:click="nextStep" />
                </div>
            </x-slot:actions>
        </x-card>
    @endif

    <!-- Step 3: Collection & Copies -->
    @if($currentStep === 3)
        <x-card title="Paso 3: Colección y Gestión de Ejemplares" subtitle="Asocie a una colección e indique cuántos ejemplares desea dar de alta simultáneamente.">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Clave de Colección (3 letras o 000)" wire:model.live="collection_code" placeholder="Ej: BIB, JUV o 000" icon="o-folder" hint="Si no pertenece a ninguna colección, deje 000" />
                
                @if(!$book)
                    <x-input label="Cantidad de Ejemplares a crear *" type="number" min="1" max="50" wire:model.live="copies_count" icon="o-document-duplicate" hint="Generará correlativos automáticos E01, E02..." />
                @endif
                
                <x-input label="Precio / Valor Unitario ($)" type="number" step="0.01" wire:model="price" icon="o-currency-dollar" />
                <x-input label="Origen / Procedencia *" wire:model="origin" placeholder="Ej: Compra Cooperadora, Donación Ministerio" />
                
                @php
                    $containerService = app(BookContainerService::class);
                    $containerOptions = $containerService->getOptions();
                @endphp
                
                <x-select 
                    label="Contenedor / Tipo de Encuadernación" 
                    wire:model="container" 
                    :options="$containerOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="Seleccione el tipo de encuadernación/soporte"
                    icon="o-archive-box"
                />
                
                <x-input label="Enlace Recurso Digital (URL PDF / E-book)" wire:model="digital" placeholder="https://..." />

                @if($book)
                    <x-input label="Fecha de Baja" type="date" wire:model="discharge_date" />
                    <x-input label="Motivo de Baja" wire:model="discharge_reason" />
                @endif
                
                <div class="md:col-span-2">
                    <x-input label="Notas Internas de Inventario" wire:model="note" placeholder="Estado de conservación, donante, etc." />
                </div>
            </div>

            <x-slot:actions>
                <div class="flex justify-between w-full">
                    <x-button label="Atrás" icon="o-arrow-left" wire:click="previousStep" />
                    <x-button label="Ver Resumen y Tejuelo" icon-right="o-arrow-right" class="btn-primary" wire:click="nextStep" />
                </div>
            </x-slot:actions>
        </x-card>
    @endif

    <!-- Step 4: Summary & Physical Spine Label Preview -->
    @if($currentStep === 4)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 1 Column: Simulated Physical Spine Label (Tejuelo) -->
            <div class="lg:col-span-1">
                <x-card title="Tejuelo Físico Escolar" subtitle="Vista previa de la etiqueta para el lomo del libro">
                    <div class="flex flex-col items-center justify-center p-6 bg-slate-100 rounded-xl border border-slate-300 dark:bg-slate-900 dark:border-slate-800">
                        
                        <!-- Physical Spine Label Box (3x5 ratio style) -->
                        <div class="w-36 h-48 bg-white border-2 border-dashed border-slate-400 rounded-md shadow-md flex flex-col justify-between p-3 text-center text-slate-900 font-mono select-none relative">
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[9px] px-2 py-0.5 rounded uppercase tracking-wider font-sans">
                                Tejuelo 3x5 cm
                            </div>

                            <!-- Line 1: Section -->
                            <div class="border-b border-slate-200 pb-1 pt-1">
                                <span class="text-xs font-black text-slate-500 block">SECCIÓN</span>
                                <span class="text-base font-bold tracking-widest text-primary">{{ $this->spineLabel['section'] }}</span>
                            </div>

                            <!-- Line 2: CDU -->
                            <div class="border-b border-slate-200 py-1">
                                <span class="text-xs font-black text-slate-500 block">CDU</span>
                                <span class="text-base font-bold tracking-wider text-slate-800">{{ $this->spineLabel['cdu'] }}</span>
                            </div>

                            <!-- Line 3: Cutter -->
                            <div class="border-b border-slate-200 py-1">
                                <span class="text-xs font-black text-slate-500 block">CUTTER</span>
                                <span class="text-base font-bold tracking-widest text-emerald-700">{{ $this->spineLabel['cutter'] }}</span>
                            </div>

                            <!-- Line 4: Collection & Exemplar -->
                            <div class="pt-1 flex justify-between text-[11px] font-bold text-slate-600">
                                <span>COL: {{ $this->spineLabel['collection'] }}</span>
                                <span class="text-amber-700">E01..</span>
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <x-badge value="Smart Key Base" class="badge-primary font-bold text-xs" />
                            <p class="font-mono text-sm font-bold text-base-content mt-1">{{ $this->baseSignature }}</p>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Right 2 Columns: Summary & Exemplars Table -->
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Resumen de Catalogación Escolar" subtitle="Verifique los datos antes de confirmar el guardado masivo en la base de datos.">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm mb-4">
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">Título</span>
                            <span class="font-bold">{{ $title }}</span>
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">Autor</span>
                            <span class="font-bold">{{ $author }}</span>
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">Editorial</span>
                            <span class="font-bold">{{ $publisher }}</span>
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">CDU / Materia</span>
                            <span class="font-bold">{{ $cdu }} / {{ $gender }}</span>
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">Colección</span>
                            <span class="font-bold">{{ $collection_code }}</span>
                        </div>
                        <div class="bg-base-200 p-3 rounded-lg">
                            <span class="text-xs text-base-content/60 block font-semibold">Ejemplares</span>
                            <span class="font-bold text-primary">{{ $book ? '1 unidad (Edición)' : "{$copies_count} unidad(es)" }}</span>
                        </div>
                    </div>

                    <div class="border-t border-base-200 pt-4">
                        <h4 class="font-bold text-sm mb-3 flex items-center gap-2">
                            <x-icon name="o-table-cells" class="w-4 h-4 text-primary" />
                            Listado de Ejemplares y Signaturas Generadas:
                        </h4>

                        <div class="overflow-x-auto max-h-60 rounded-lg border border-base-200">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Código Ejemplar</th>
                                        <th>Signatura Topográfica (Smart Key)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($generatedExemplars as $item)
                                        <tr>
                                            <td class="font-bold">{{ $item['exemplar_number'] }}</td>
                                            <td><x-badge :value="$item['exemplar_code']" class="badge-neutral" /></td>
                                            <td class="font-mono font-bold text-primary">{{ $item['signature'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <x-slot:actions>
                        <div class="flex justify-between w-full">
                            <x-button label="Atrás" icon="o-arrow-left" wire:click="previousStep" />
                            <div class="flex gap-2">
                                @if($book)
                                    <x-button label="Eliminar Libro" icon="o-trash" class="btn-error btn-outline" wire:click="delete" wire:confirm="¿Estás seguro de eliminar este libro?" />
                                @endif
                                <x-button :label="$book ? 'Guardar Cambios' : 'Confirmar y Guardar ' . $copies_count . ' Libro(s)'" icon="o-check" class="btn-success" wire:click="save" spinner="save" />
                            </div>
                        </div>
                    </x-slot:actions>
                </x-card>
            </div>
        </div>
    @endif
</div>
