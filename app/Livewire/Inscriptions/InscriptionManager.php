<?php

namespace App\Livewire\Inscriptions;

use App\Models\Career;
use App\Models\Config;
use App\Models\Inscription;
use App\Models\Subject;
use App\Services\InscriptionCatalog;
use App\Services\InscriptionPdfService;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class InscriptionManager extends Component
{
    use AuthorizesAccess, Toast;

    private const CATALOG_ROLES = ['admin', 'principal', 'director', 'administrative'];

    public string $search = '';

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    #[Url]
    public $inscription_id = null;

    #[Url]
    public $career_id = null;

    #[Url]
    public $user_id = null;

    /**
     * Estado editable por materia.
     *
     * @var array<int, array{value: ?string, type: string, selected: mixed}>
     */
    public array $rows = [];

    /**
     * Última versión guardada, para detectar cambios sin guardar.
     *
     * @var array<int, array{value: ?string, type: string, selected: mixed}>
     */
    public array $savedRows = [];

    public array $types = [
        [
            'id' => InscriptionCatalog::TYPE_SINGLE,
            'name' => 'Acepta solo un valor',
        ],
        [
            'id' => InscriptionCatalog::TYPE_MULTIPLE,
            'name' => 'Acepta varios valores',
        ],
    ];

    public bool $pdfExists = false;

    public string $pdfFileName = '';

    /**
     * Indica si había selecciones guardadas que ya no están en el catálogo.
     */
    public bool $hasStaleSelections = false;

    public function mount(): void
    {
        if (! $this->targetUser->enabled) {
            return;
        }

        $this->career_id = $this->resolveCareerId($this->career_id);
        $this->inscription_id = $this->resolveInscriptionId($this->inscription_id);

        if (! $this->inscription_id) {
            $this->warning('No se han encontrado Inscripciones abiertas.');
            $this->checkPdf();

            return;
        }

        $this->loadRows();
        $this->checkPdf();
    }

    #[Computed]
    public function targetUser()
    {
        return $this->getTargetUser($this->user_id);
    }

    #[Computed]
    public function canManageCatalog(): bool
    {
        return auth()->user()->hasAnyRole(self::CATALOG_ROLES);
    }

    #[Computed]
    public function inscriptions(): Collection
    {
        $periods = Config::getGroup('inscriptions');

        if ($this->canManageCatalog) {
            return $periods;
        }

        return $periods
            ->filter(fn (Config $period): bool => filter_var($period->value, FILTER_VALIDATE_BOOLEAN))
            ->values();
    }

    #[Computed]
    public function careers(): Collection
    {
        if ($this->canManageCatalog) {
            return Career::where('allow_enrollments', true)
                ->where('allow_evaluations', true)
                ->get();
        }

        return $this->targetUser->careers
            ->filter(fn (Career $career): bool => $career->allow_enrollments && $career->allow_evaluations)
            ->values();
    }

    /**
     * Materias de la carrera con el catálogo del ADMIN y la selección del estudiante.
     */
    #[Computed]
    public function subjectRows(): Collection
    {
        if (! $this->career_id || ! $this->inscription_id) {
            return collect();
        }

        $search = Str::of($this->search)->trim();

        $subjects = Subject::query()
            ->where('career_id', $this->career_id)
            ->where('name', '!=', '')
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $term = '%'.$search->lower()->ascii().'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('id', 'like', $term);
                });
            })
            ->orderBy('id')
            ->get();

        $catalog = app(InscriptionCatalog::class);
        $catalogRows = $catalog->forSubjects((string) $this->inscription_id, $subjects->pluck('id')->all());

        foreach ($subjects as $subject) {
            $row = $this->rows[$subject->id] ?? [];
            $catalogRow = $catalogRows->get($subject->id);
            $type = $this->canManageCatalog
                ? ($row['type'] ?? $catalog->mode($catalogRow))
                : $catalog->mode($catalogRow);

            $subject->value = $this->canManageCatalog
                ? (array_key_exists('value', $row) ? $row['value'] : $catalogRow?->value)
                : $catalogRow?->value;

            $subject->type = $type;
            $subject->options = collect($catalog->options($catalogRow?->value))
                ->map(fn (string $option): array => ['id' => $option, 'name' => $option])
                ->all();

            if ($this->canManageCatalog) {
                $subject->selected = null;
                $subject->preview = $catalog->options(
                    is_string($subject->value) ? $subject->value : null,
                );

                continue;
            }

            $subject->preview = [];

            $selected = $row['selected'] ?? null;
            $subject->selected = $type === InscriptionCatalog::TYPE_MULTIPLE
                ? $catalog->normalizeSelection($selected)
                : (is_array($selected) ? null : $selected);
        }

        if (! $this->canManageCatalog) {
            return $subjects
                ->filter(fn (Subject $subject): bool => $subject->options !== [])
                ->values();
        }

        return $subjects;
    }

    /**
     * Cantidad de materias con y sin opciones en el catálogo del ADMIN.
     *
     * @return array{total: int, with: int, without: int}
     */
    #[Computed]
    public function catalogSummary(): array
    {
        $with = collect($this->rows)
            ->filter(fn (array $row): bool => trim((string) ($row['value'] ?? '')) !== '')
            ->count();

        return [
            'total' => count($this->rows),
            'with' => $with,
            'without' => count($this->rows) - $with,
        ];
    }

    #[Computed]
    public function selectedCount(): int
    {
        if ($this->canManageCatalog) {
            return 0;
        }

        $catalog = app(InscriptionCatalog::class);

        return collect($this->rows)
            ->filter(fn (array $row): bool => $catalog->normalizeSelection($row['selected'] ?? null) !== [])
            ->count();
    }

    public function hasUnsavedChanges(): bool
    {
        return $this->rows !== $this->savedRows;
    }

    public function checkPdf(): void
    {
        if ($this->targetUser && $this->career_id && $this->inscription_id) {
            $this->pdfFileName = InscriptionPdfService::fileName(
                $this->targetUser->id,
                (int) $this->career_id,
                (string) $this->inscription_id,
            );
            $this->pdfExists = InscriptionPdfService::exists(
                $this->targetUser->id,
                (int) $this->career_id,
                (string) $this->inscription_id,
            );

            return;
        }

        $this->pdfExists = false;
    }

    public function updatedCareerId(): void
    {
        $this->reloadRows();
    }

    public function updatedInscriptionId(): void
    {
        $this->reloadRows();
    }

    /**
     * Descarta los cambios locales y vuelve a leer el estado guardado.
     */
    public function discardChanges(): void
    {
        $this->loadRows();
        $this->resetValidation();
        $this->checkPdf();
    }

    public function headers(): array
    {
        $labelSubject = Config::get('label_subject', 'Materia');

        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'name', 'label' => $labelSubject],
            ['key' => 'type', 'label' => 'Tipo', 'sortable' => false],
            ['key' => 'value', 'label' => 'Valor', 'sortable' => false],
        ];
    }

    public function save(): void
    {
        if (! $this->canManageCatalog && $this->selectedCount === 0 && ! $this->hasUnsavedChanges() && ! $this->hasStaleSelections) {
            $this->info('No hay selecciones para guardar.');

            return;
        }

        $catalog = app(InscriptionCatalog::class);
        $selectionsChanged = ! $this->canManageCatalog && ($this->hasUnsavedChanges() || $this->hasStaleSelections);

        $this->validateInscriptions($catalog);
        $this->persistInscriptions($catalog);

        $this->savedRows = $this->rows;
        $this->hasStaleSelections = false;

        if ($selectionsChanged) {
            $this->invalidatePdf();
        }

        $this->success(
            $this->canManageCatalog
                ? 'Inscripciones actualizadas'
                : 'Selección guardada',
        );

        $this->checkPdf();
    }

    public function saveAndConfirm()
    {
        if ($this->selectedCount === 0 && ! $this->hasUnsavedChanges()) {
            $this->warning('No has seleccionado ninguna materia.');

            return null;
        }

        $catalog = app(InscriptionCatalog::class);

        $this->validateInscriptions($catalog);
        $this->persistInscriptions($catalog);

        $this->savedRows = $this->rows;

        $hasInscriptions = Inscription::where('configs_id', $this->inscription_id)
            ->where('user_id', $this->targetUser->id)
            ->whereIn('subject_id', function ($query): void {
                $query->select('id')->from('subjects')->where('career_id', $this->career_id);
            })->exists();

        if (! $hasInscriptions) {
            $this->invalidatePdf();
            $this->success('Todas las selecciones eliminadas correctamente.');

            return null;
        }

        $this->invalidatePdf();

        return redirect()->route('inscriptionsSavePDF', [
            'student' => $this->targetUser->id,
            'career' => $this->career_id,
            'inscription' => $this->inscription_id,
        ]);
    }

    /**
     * Elimina el PDF guardado cuando la selección cambia, para no servir uno obsoleto.
     */
    private function invalidatePdf(): void
    {
        if (! $this->targetUser || ! $this->career_id || ! $this->inscription_id) {
            return;
        }

        InscriptionPdfService::delete(
            $this->targetUser->id,
            (int) $this->career_id,
            (string) $this->inscription_id,
        );

        $this->checkPdf();
    }

    public function clearSelection(int $subject_id): void
    {
        if (! isset($this->rows[$subject_id])) {
            return;
        }

        $this->rows[$subject_id]['selected'] = $this->rows[$subject_id]['type'] === InscriptionCatalog::TYPE_MULTIPLE
            ? []
            : null;
    }

    private function reloadRows(): void
    {
        $hadUnsavedChanges = $this->hasUnsavedChanges();

        $this->rows = [];
        $this->loadRows();
        $this->resetValidation();
        $this->checkPdf();

        if ($hadUnsavedChanges) {
            $this->warning('Se descartaron los cambios sin guardar.');
        }
    }

    private function loadRows(): void
    {
        $this->rows = [];
        $this->hasStaleSelections = false;

        if (! $this->career_id || ! $this->inscription_id) {
            $this->savedRows = [];

            return;
        }

        $subjects = Subject::where('career_id', $this->career_id)->get();
        $catalog = app(InscriptionCatalog::class);
        $catalogRows = $catalog->forSubjects((string) $this->inscription_id, $subjects->pluck('id')->all());

        $selections = $this->canManageCatalog
            ? collect()
            : Inscription::where('configs_id', $this->inscription_id)
                ->where('user_id', $this->targetUser->id)
                ->whereIn('subject_id', $subjects->pluck('id')->all())
                ->get()
                ->keyBy('subject_id');

        $droppedSelections = 0;

        foreach ($subjects as $subject) {
            $catalogRow = $catalogRows->get($subject->id);
            $type = $catalog->mode($catalogRow);
            $options = $catalog->options($catalogRow?->value);
            $storedValue = $selections->get($subject->id)?->value;
            $selected = $storedValue;

            if ($this->canManageCatalog) {
                $this->rows[$subject->id] = [
                    'value' => $catalogRow?->value,
                    'type' => $type,
                    'selected' => null,
                ];

                continue;
            }

            $selected = $type === InscriptionCatalog::TYPE_MULTIPLE
                ? array_values(array_intersect($options, $catalog->normalizeSelection($storedValue)))
                : (in_array($storedValue, $options, true) ? $storedValue : null);

            $storedOptions = $type === InscriptionCatalog::TYPE_MULTIPLE
                ? $catalog->options($storedValue)
                : $catalog->normalizeSelection($storedValue);
            $validOptions = $catalog->normalizeSelection($selected);

            if ($storedOptions !== $validOptions) {
                $droppedSelections++;
            }

            $this->rows[$subject->id] = [
                'value' => $catalogRow?->value,
                'type' => $type,
                'selected' => $selected,
            ];
        }

        if ($droppedSelections > 0) {
            $this->hasStaleSelections = true;
            $this->warning("Se quitaron {$droppedSelections} selecciones que ya no están disponibles en el catálogo.");
        }

        $this->savedRows = $this->rows;
    }

    private function validateInscriptions(InscriptionCatalog $catalog): void
    {
        $this->normalizeEmptySelections();

        if (! $this->canManageCatalog && ! $catalog->isOpen((string) $this->inscription_id)) {
            throw ValidationException::withMessages([
                'inscription_id' => 'La inscripción seleccionada está cerrada.',
            ]);
        }

        $rules = [
            'career_id' => ['required', Rule::in($this->careers->pluck('id')->all())],
            'inscription_id' => ['required', Rule::in($this->inscriptions->pluck('id')->all())],
        ];

        $messages = [];

        $subjectIds = Subject::where('career_id', $this->career_id)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rowIds = array_map('intval', array_keys($this->rows));
        $allowedSubjectIds = array_values(array_intersect($subjectIds, $rowIds));
        $catalogRows = $catalog->forSubjects((string) $this->inscription_id, $allowedSubjectIds);

        $unavailableMessage = 'La opción seleccionada ya no está disponible. Vuelve a elegirla.';

        foreach ($allowedSubjectIds as $subjectId) {
            if ($this->canManageCatalog) {
                $rules["rows.{$subjectId}.value"] = ['nullable', 'string', 'max:250'];
                $rules["rows.{$subjectId}.type"] = ['required', Rule::in([
                    InscriptionCatalog::TYPE_SINGLE,
                    InscriptionCatalog::TYPE_MULTIPLE,
                ])];
                $messages["rows.{$subjectId}.type.required"] = 'Debes indicar el tipo de selección de la materia.';

                continue;
            }

            $catalogRow = $catalogRows->get($subjectId);

            if (! $catalogRow) {
                continue;
            }

            $options = $catalog->options($catalogRow->value);
            $type = $catalog->mode($catalogRow);

            $rules["rows.{$subjectId}.selected"] = $type === InscriptionCatalog::TYPE_SINGLE
                ? ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($options, $unavailableMessage): void {
                    if (! in_array($value, $options, true)) {
                        $fail($unavailableMessage);
                    }
                }]
                : ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($options, $unavailableMessage): void {
                    foreach ((array) $value as $option) {
                        if (! in_array($option, $options, true)) {
                            $fail($unavailableMessage);

                            return;
                        }
                    }
                }];
        }

        $this->validate($rules, $messages);
    }

    private function normalizeEmptySelections(): void
    {
        foreach ($this->rows as $subjectId => $row) {
            if (($row['selected'] ?? null) === '') {
                $this->rows[$subjectId]['selected'] = null;
            }
        }
    }

    private function persistInscriptions(InscriptionCatalog $catalog): void
    {
        $subjectIds = Subject::where('career_id', $this->career_id)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rowIds = array_map('intval', array_keys($this->rows));
        $allowedSubjectIds = array_values(array_intersect($subjectIds, $rowIds));
        $catalogRows = $catalog->forSubjects((string) $this->inscription_id, $allowedSubjectIds);

        $userId = $this->canManageCatalog
            ? Inscription::CATALOG_USER_ID
            : $this->targetUser->id;

        $upserts = [];
        $emptySubjectIds = [];

        foreach ($allowedSubjectIds as $subjectId) {
            $row = $this->rows[$subjectId] ?? [];
            $catalogRow = $catalogRows->get($subjectId);

            if (! $this->canManageCatalog && ! $catalogRow) {
                $emptySubjectIds[] = $subjectId;

                continue;
            }

            $type = $this->canManageCatalog
                ? ($row['type'] ?? $catalog->mode($catalogRow))
                : $catalog->mode($catalogRow);

            $value = $this->canManageCatalog
                ? ($type === InscriptionCatalog::TYPE_MULTIPLE
                    ? implode(',', $catalog->options($row['value'] ?? null))
                    : trim((string) ($row['value'] ?? '')))
                : implode(',', $catalog->normalizeSelection($row['selected'] ?? null));

            if ($this->canManageCatalog) {
                $this->rows[$subjectId]['value'] = $value === '' ? null : $value;
            }

            if ($value === '') {
                $emptySubjectIds[] = $subjectId;

                continue;
            }

            $upserts[] = [
                'subject_id' => $subjectId,
                'user_id' => $userId,
                'configs_id' => (string) $this->inscription_id,
                'type' => $type,
                'value' => $value,
            ];
        }

        DB::transaction(function () use ($upserts, $emptySubjectIds, $userId): void {
            if ($emptySubjectIds !== []) {
                Inscription::where('configs_id', $this->inscription_id)
                    ->where('user_id', $userId)
                    ->whereIn('subject_id', $emptySubjectIds)
                    ->delete();
            }

            if ($upserts !== []) {
                Inscription::upsert(
                    $upserts,
                    ['subject_id', 'user_id', 'configs_id'],
                    ['type', 'value'],
                );
            }
        });
    }

    private function resolveCareerId(mixed $careerId): ?int
    {
        $career = $this->careers->firstWhere('id', (int) $careerId);

        if ($career) {
            return (int) $career->id;
        }

        return $this->careers->first()?->id;
    }

    private function resolveInscriptionId(mixed $inscriptionId): ?string
    {
        $period = $this->inscriptions->firstWhere('id', (string) $inscriptionId);

        if ($period) {
            return (string) $period->id;
        }

        return $this->inscriptions->first()?->id;
    }

    public function render()
    {
        return view('livewire.inscriptions.inscription-manager', [
            'items' => $this->subjectRows,
            'headers' => $this->headers(),
        ]);
    }
}
