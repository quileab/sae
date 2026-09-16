<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\Subject;
use App\Models\Enrollment;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public $subjects = [];
    public $selectedSubjectId = null;
    public $enrollments = [];
    public array $grades = [];
    public array $dates = [];
    public $globalDate = null;
    public $search = '';

    public function mount()
    {
        $this->globalDate = date('Y-m-d');
        $this->searchSubjects();
    }

    public function searchSubjects(string $value = '')
    {
        $selectedOption = Subject::with('career')->where('id', $this->selectedSubjectId)->get();

        $query = Subject::with('career')
            ->where('name', '!=', '')
            ->whereNotNull('name');

        if (trim($value)) {
            $query->where(function($q) use ($value) {
                $q->where('name', 'like', "%$value%")
                  ->orWhereHas('career', function($qCareer) use($value) {
                      $qCareer->where('name', 'like', "%$value%");
                  });
            });
        }

        $this->subjects = $query->take(50)->get()->merge($selectedOption)->map(function($subject) {
            $careerName = $subject->career ? $subject->career->name : 'Sin Carrera';
            return [
                'id' => $subject->id,
                'name' => $careerName . ' - ' . $subject->name,
                'career_name' => $careerName,
                'subject_name' => $subject->name
            ];
        })
        ->unique('id')
        ->sortBy(['career_name', 'subject_name'])
        ->values()
        ->toArray();
    }

    public function updatedSelectedSubjectId($value)
    {
        if ($value) {
            $this->loadEnrollments();
        } else {
            $this->enrollments = [];
            $this->grades = [];
            $this->dates = [];
        }
    }

    public function updatedGlobalDate($value)
    {
        if ($value && !empty($this->enrollments)) {
            foreach ($this->enrollments as $enrollment) {
                $this->dates[$enrollment->id] = $value;
            }
        }
    }

    public function loadEnrollments()
    {
        $this->enrollments = Enrollment::with('user')
            ->where('subject_id', $this->selectedSubjectId)
            ->whereIn('status', ['active', 'completed'])
            ->get()
            ->filter(function ($enrollment) {
                return $enrollment->user && $enrollment->user->hasRole('student');
            })
            ->sortBy(function ($enrollment) {
                return mb_strtolower($enrollment->user->fullname ?? $enrollment->user->name ?? '');
            })
            ->values();

        $this->grades = [];
        $this->dates = [];
        foreach ($this->enrollments as $enrollment) {
            $this->grades[$enrollment->id] = $enrollment->final_grade;
            $existingDate = $enrollment->final_grade_date ? $enrollment->final_grade_date->format('Y-m-d') : null;
            $this->dates[$enrollment->id] = $existingDate ?: $this->globalDate;
        }
    }

    public function saveGrades()
    {
        foreach ($this->grades as $enrollmentId => $grade) {
            // Permitir guardar notas nulas (borrar) o números
            $value = ($grade === '' || $grade === null) ? null : $grade;
            $dateValue = (!empty($this->dates[$enrollmentId])) ? $this->dates[$enrollmentId] : null;
            
            Enrollment::where('id', $enrollmentId)->update([
                'final_grade' => $value,
                'final_grade_date' => $dateValue
            ]);
        }

        $this->success('Notas guardadas correctamente.', '¡Éxito!');
    }

    public function deleteGrade($enrollmentId)
    {
        Enrollment::where('id', $enrollmentId)->update([
            'final_grade' => null,
            'final_grade_date' => null,
        ]);

        $this->loadEnrollments();
        $this->success('Nota eliminada correctamente.');
    }

    public function deleteEnrollment($enrollmentId)
    {
        Enrollment::where('id', $enrollmentId)->delete();

        $this->loadEnrollments();
        $this->success('Inscripción eliminada correctamente.');
    }

    #[Computed]
    public function filteredEnrollments()
    {
        $collection = collect($this->enrollments);

        if (! empty($this->search)) {
            $searchTerm = mb_strtolower($this->search);
            $collection = $collection->filter(function ($enrollment) use ($searchTerm) {
                $name = mb_strtolower($enrollment->user->fullname ?? $enrollment->user->name ?? '');
                return str_contains($name, $searchTerm);
            });
        }

        return $collection->sortBy(function ($enrollment) {
            return mb_strtolower($enrollment->user->fullname ?? $enrollment->user->name ?? '');
        })->values();
    }
}; ?>

<div>
    {{-- HEADER CON ACCIÓN DE GUARDADO --}}
    <x-header title="Cierre de Actas" class="mb-1">
        @if($selectedSubjectId && $this->filteredEnrollments->count() > 0)
            <x-slot:actions>
                <x-button 
                    label="Guardar Actas" 
                    icon="o-check" 
                    class="btn-primary btn-sm" 
                    wire:click="saveGrades" 
                    spinner="saveGrades" 
                />
            </x-slot:actions>
        @endif
    </x-header>

    {{-- PANEL UNIFICADO: FILTROS + TABLA --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
        {{-- FILTROS COMPACTOS EN CABECERA DEL PANEL --}}
        <div class="p-3 bg-base-100 border-b border-base-200">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-6">
                    <x-choices 
                        label="Materia"
                        placeholder="Buscar materia..."
                        icon="o-book-open"
                        :options="$subjects"
                        option-value="id"
                        option-label="name"
                        wire:model.live="selectedSubjectId"
                        single
                        searchable
                        search-function="searchSubjects"
                    />
                </div>

                <div class="md:col-span-3">
                    <x-input 
                        type="date" 
                        label="Fecha Examen" 
                        icon="o-calendar"
                        wire:model.live="globalDate" 
                    />
                </div>

                @if($selectedSubjectId)
                    <div class="md:col-span-3">
                        <x-input 
                            placeholder="Filtrar alumno..." 
                            wire:model.live.debounce="search" 
                            icon="o-magnifying-glass" 
                            clearable 
                        />
                    </div>
                @endif
            </div>
        </div>

        {{-- LISTA PRINCIPAL DE ESTUDIANTES --}}
        @if($selectedSubjectId)
            @if($this->filteredEnrollments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full text-sm">
                        <thead>
                            <tr class="bg-base-200/50 text-base-content/70 uppercase text-xs">
                                <th class="py-3 pl-4">Estudiante</th>
                                <th class="py-3">Estado</th>
                                <th class="py-3 w-44">Fecha Examen</th>
                                <th class="py-3 w-36">Nota Final</th>
                                <th class="py-3 text-right pr-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            @foreach($this->filteredEnrollments as $enrollment)
                                <tr class="hover:bg-base-200/30 transition-colors">
                                    <td class="py-2.5 pl-4 font-medium">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $enrollment->user->avatar_url }}" class="w-8 h-8 rounded-full object-cover border border-base-300 shrink-0" />
                                            <div>
                                                <div class="font-semibold text-base-content">{{ $enrollment->user->fullname ?? $enrollment->user->name }}</div>
                                                @if(isset($enrollment->user->email))
                                                    <div class="text-xs opacity-50">{{ $enrollment->user->email }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        @if($enrollment->status === 'completed')
                                            <span class="badge badge-success badge-sm gap-1">
                                                <x-icon name="o-check-circle" class="w-3 h-3" /> Completado
                                            </span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">Activo</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5">
                                        <x-input 
                                            type="date" 
                                            class="input-sm"
                                            wire:model="dates.{{ $enrollment->id }}" 
                                        />
                                    </td>
                                    <td class="py-2.5">
                                        <x-input 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            max="10"
                                            class="input-sm font-bold text-center"
                                            wire:model="grades.{{ $enrollment->id }}" 
                                            placeholder="—"
                                        />
                                    </td>
                                    <td class="py-2.5 text-right pr-4">
                                        <div class="flex items-center justify-end gap-1">
                                            @if($enrollment->final_grade !== null)
                                                <x-dropdown icon="o-backspace" class="btn-warning btn-ghost btn-xs text-warning" tooltip="Borrar Nota">
                                                    <x-menu-item 
                                                        title="Confirmar borrar nota" 
                                                        icon="o-check" 
                                                        wire:click="deleteGrade({{ $enrollment->id }})" 
                                                        class="text-warning font-semibold" 
                                                    />
                                                </x-dropdown>
                                            @endif

                                            <x-dropdown icon="o-user-minus" class="btn-error btn-ghost btn-xs text-error" tooltip="Desmatricular Alumno">
                                                <x-menu-item 
                                                    title="Confirmar desmatriculación" 
                                                    icon="o-user-minus" 
                                                    wire:click="deleteEnrollment({{ $enrollment->id }})" 
                                                    class="text-error font-semibold" 
                                                />
                                            </x-dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- PIE DE PÁGINA CON RESUMEN Y BOTÓN SECUNDARIO --}}
                <div class="p-3 bg-base-200/40 border-t border-base-200 flex justify-between items-center text-xs opacity-70">
                    <span>Mostrando {{ $this->filteredEnrollments->count() }} estudiante(s)</span>
                    <x-button 
                        label="Guardar Cambios" 
                        icon="o-check" 
                        class="btn-primary btn-sm" 
                        wire:click="saveGrades" 
                        spinner="saveGrades" 
                    />
                </div>
            @else
                <div class="p-8 text-center">
                    <x-icon name="o-user-minus" class="w-12 h-12 mx-auto text-base-content/30 mb-2" />
                    <p class="font-medium text-base-content/70">No se encontraron estudiantes para los criterios seleccionados.</p>
                </div>
            @endif
        @else
            <div class="p-12 text-center">
                <x-icon name="o-academic-cap" class="w-16 h-16 mx-auto text-primary/40 mb-3" />
                <h3 class="font-bold text-lg text-base-content/80">Seleccione una Materia</h3>
                <p class="text-sm text-base-content/50 max-w-sm mx-auto mt-1">
                    Elija una asignatura arriba para desplegar la lista completa de estudiantes e ingresar sus notas finales.
                </p>
            </div>
        @endif
    </div>
</div>

<style>
    /* Resaltado visual al navegar con teclado o mouse en las opciones de x-choices */
    div[wire\:key^="option-"]:hover,
    div[wire\:key^="option-"]:focus {
        background-color: var(--color-primary-10, rgba(59, 130, 246, 0.15)) !important;
        border-left-color: #3b82f6 !important;
        outline: none !important;
    }
</style>
