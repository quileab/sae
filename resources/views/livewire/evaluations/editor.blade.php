<?php

use App\Enums\EvaluationGradingMethod;
use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\ClassSession;
use App\Services\Evaluations\EvaluationContent;
use App\Services\Evaluations\MarkdownImporter;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?OnlineEvaluation $evaluation = null;

    public $subject_id;
    public $class_session_id;
    public $title = '';
    public $description = '';
    public $starts_at = null;
    public $ends_at = null;
    public $duration_minutes = 60;
    public $status = EvaluationStatus::Draft->value;

    // Moodle advanced settings
    public $passing_score = 6.0;
    public $max_attempts = 1;
    public $grading_method = EvaluationGradingMethod::Highest->value;
    public $shuffle_questions = false;

    public $markdown_content = '';

    // El contenido principal: array de secciones
    public $sections = [];

    /**
     * Which editor panel is open: data, rules or content.
     */
    public $activeTab = 'data';

    /**
     * Whether the form differs from what was loaded, so the UI can warn before
     * discarding work.
     */
    public $isDirty = false;

    public $totalPoints = 0;

    public function mount($id = null)
    {
        if ($id) {
            $this->evaluation = OnlineEvaluation::findOrFail($id);
            $this->authorize('update', $this->evaluation);

            $this->subject_id = $this->evaluation->subject_id;
            $this->class_session_id = $this->evaluation->class_session_id;
            $this->title = $this->evaluation->title;
            $this->description = $this->evaluation->description;
            $this->starts_at = $this->evaluation->starts_at ? $this->evaluation->starts_at->format('Y-m-d\TH:i') : null;
            $this->ends_at = $this->evaluation->ends_at ? $this->evaluation->ends_at->format('Y-m-d\TH:i') : null;
            $this->duration_minutes = $this->evaluation->duration_minutes;
            $this->status = $this->evaluation->status;

            $this->passing_score = $this->evaluation->passing_score;
            $this->max_attempts = $this->evaluation->max_attempts;
            $this->grading_method = $this->evaluation->grading_method;
            $this->shuffle_questions = $this->evaluation->shuffle_questions;

            $content = $this->evaluation->content ?? [];
            $this->sections = $content['sections'] ?? [];
            if (empty($this->sections)) {
                $this->addSection();
            }
        } else {
            $this->subject_id = request()->query('subject_id') ?? session('current_subject_id');
            if (!$this->subject_id) {
                return $this->redirectRoute('evaluations.index');
            }
            $this->addSection();
        }
    }

    public function updated($property): void
    {
        $this->isDirty = true;
    }

    #[Computed]
    public function totalPoints()
    {
        return EvaluationContent::getMaxScore(['sections' => array_values($this->sections)]);
    }

    #[Computed]
    public function questionCount()
    {
        return collect($this->sections)
            ->sum(fn (array $section) => count($section['blocks'] ?? []));
    }

    public function getSubjectProperty()
    {
        return Subject::find($this->subject_id);
    }

    public function getClassSessionsProperty()
    {
        return ClassSession::where('subject_id', $this->subject_id)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function($session) {
                return [
                    'id' => $session->id,
                    'name' => "Clase #{$session->class_number} - " . $session->date->format('d/m/Y')
                ];
            });
    }

    public function addSection()
    {
        $this->sections[] = [
            'id' => uniqid('sec_'),
            'title' => 'Nueva Sección',
            'intro' => '',
            'blocks' => []
        ];
    }

    public function removeSection($index)
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
    }

    public function addBlock($sectionIndex, $type)
    {
        $block = [
            'id' => uniqid('blk_'),
            'type' => $type,
            'prompt' => '',
            'points' => 1,
        ];

        switch ($type) {
            case 'multiple_choice':
                $block['options'] = [
                    ['key' => 'A', 'text' => ''],
                    ['key' => 'B', 'text' => '']
                ];
                $block['correct_option'] = 'A';
                $block['explanation'] = '';
                break;
            case 'true_false':
                $block['correct_option'] = 'true';
                $block['explanation'] = '';
                break;
            case 'checkboxes':
                $block['options'] = [
                    ['key' => 'A', 'text' => ''],
                    ['key' => 'B', 'text' => '']
                ];
                $block['correct_options'] = ['A'];
                break;
            case 'matching':
                $block['pairs'] = [
                    ['prompt' => 'Término 1', 'answer' => 'Concepto 1']
                ];
                break;
            case 'table_design':
                $block['attributes'] = [];
                $block['tables'] = [];
                $block['relations'] = [];
                $block['relation_types'] = ['1:1', '1:N', 'N:N'];
                $block['answer_key'] = [
                    'tables' => [],
                    'relations' => []
                ];
                break;
            case 'open_text':
            case 'short_text':
                $block['variants'] = [];
                break;
        }

        $this->sections[$sectionIndex]['blocks'][] = $block;
    }

    public function removeBlock($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex];

        unset($block['blocks'][$blockIndex]);
        $block['blocks'] = array_values($block['blocks']);
    }

public function addPair($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $block['pairs'][] = ['prompt' => '', 'answer' => ''];
    }

public function removePair($sectionIndex, $blockIndex, $pairIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        unset($block['pairs'][$pairIndex]);
        $block['pairs'] = array_values($block['pairs']);
    }

public function addAttribute($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];
        $block['attributes'][] = '';
    }

public function removeAttribute($sectionIndex, $blockIndex, $attributeIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        unset($block['attributes'][$attributeIndex]);
        $block['attributes'] = array_values($block['attributes']);
    }

public function addSchemaTable($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $block['tables'][] = [
            'key' => 't'.(count($block['tables']) + 1),
            'name' => '',
            'rows' => 2,
        ];
    }

public function removeSchemaTable($sectionIndex, $blockIndex, $tableIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $name = $block['tables'][$tableIndex]['name'] ?? null;

        unset($block['tables'][$tableIndex]);
        $block['tables'] = array_values($block['tables']);

        // Drop the answer key entry and any relation that referenced it.
        $this->synchroniseAnswerKey($block, $name);
    }


public function addKeyTable($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $block['answer_key']['tables'][] = [
            'name' => '',
            'columns' => [],
            'pk' => null,
            'fks' => [],
        ];
    }

public function removeKeyTable($sectionIndex, $blockIndex, $tableIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $name = $block['answer_key']['tables'][$tableIndex]['name'] ?? null;

        unset($block['answer_key']['tables'][$tableIndex]);
        $block['answer_key']['tables'] = array_values($block['answer_key']['tables']);

        $this->synchroniseAnswerKey($block, $name);
    }

public function addKeyColumn($sectionIndex, $blockIndex, $tableIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];
        $block['answer_key']['tables'][$tableIndex]['columns'][] = '';
    }

public function removeKeyColumn($sectionIndex, $blockIndex, $tableIndex, $columnIndex)
    {
        $table = $this->keyTable($sectionIndex, $blockIndex, $tableIndex);

        $column = $table['columns'][$columnIndex] ?? null;

        unset($table['columns'][$columnIndex]);

        $this->writeKeyTable($sectionIndex, $blockIndex, $tableIndex, [
            ...$table,
            'columns' => array_values($table['columns']),
            'pk' => $table['pk'] === $column ? null : $table['pk'],
            'fks' => array_values(array_filter(
                $table['fks'] ?? [],
                fn ($fk) => $fk !== $column
            )),
        ]);
    }

public function addKeyRelation($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $block['answer_key']['relations'][] = [
            'from' => '',
            'to' => '',
            'type' => '1:N',
        ];
    }

public function removeKeyRelation($sectionIndex, $blockIndex, $relationIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        unset($block['answer_key']['relations'][$relationIndex]);
        $block['answer_key']['relations'] = array_values($block['answer_key']['relations']);
    }

public function toggleKeyForeignKey($sectionIndex, $blockIndex, $tableIndex, $column)
    {
        $table = $this->keyTable($sectionIndex, $blockIndex, $tableIndex);
        $foreignKeys = array_values($table['fks'] ?? []);

        $position = array_search($column, $foreignKeys, true);

        if ($position === false) {
            $foreignKeys[] = $column;
        } else {
            unset($foreignKeys[$position]);
            $foreignKeys = array_values($foreignKeys);
        }

        $this->writeKeyTable($sectionIndex, $blockIndex, $tableIndex, [
            ...$table,
            'fks' => $foreignKeys,
        ]);
    }

public function setKeyPrimaryKey($sectionIndex, $blockIndex, $tableIndex, $column)
    {
        $table = $this->keyTable($sectionIndex, $blockIndex, $tableIndex);

        $primaryKey = $table['pk'] === $column ? null : $column;

        $this->writeKeyTable($sectionIndex, $blockIndex, $tableIndex, [
            ...$table,
            'pk' => $primaryKey,
            // A column cannot be both the primary key and a foreign key.
            'fks' => $primaryKey === null
                ? array_values($table['fks'] ?? [])
                : array_values(array_filter($table['fks'] ?? [], fn ($fk) => $fk !== $primaryKey)),
        ]);
    }

public function toggleKeyRelationType($sectionIndex, $blockIndex, $relationIndex, $type)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        $relation = $block['answer_key']['relations'][$relationIndex] ?? ['from' => '', 'to' => '', 'type' => '1:N'];

        $block['answer_key']['relations'][$relationIndex] = [
            ...$relation,
            'type' => $relation['type'] === $type ? '1:N' : $type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function keyTable($sectionIndex, $blockIndex, $tableIndex): array
    {
        return $this->sections[$sectionIndex]['blocks'][$blockIndex]['answer_key']['tables'][$tableIndex] ?? [];
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function writeKeyTable($sectionIndex, $blockIndex, $tableIndex, array $table): void
    {
        // Assign through a reference: Livewire public properties are magic, so a
        // plain $this->sections[...] write would mutate a temporary and be lost.
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];
        $block['answer_key']['tables'][$tableIndex] = $table;
    }

/**
 * Keep the answer key aligned with the tables the student is asked to build:
 * dropping a table also drops its key entry and its relations.
 *
 * @param  array<string, mixed>  $block
 */
protected function synchroniseAnswerKey(array &$block, ?string $tableName): void
{
    if ($tableName === null || $tableName === '') {
        return;
    }

    $block['answer_key']['tables'] = array_values(array_filter(
        $block['answer_key']['tables'] ?? [],
        fn (array $key) => ($key['name'] ?? null) !== $tableName
    ));

    $block['answer_key']['relations'] = array_values(array_filter(
        $block['answer_key']['relations'] ?? [],
        fn (array $relation) => ($relation['from'] ?? null) !== $tableName && ($relation['to'] ?? null) !== $tableName
    ));
}

    public function moveBlockUp($sectionIndex, $blockIndex)
    {
        if ($blockIndex > 0) {
            $blocks = $this->sections[$sectionIndex]['blocks'];
            $temp = $blocks[$blockIndex - 1];
            $blocks[$blockIndex - 1] = $blocks[$blockIndex];
            $blocks[$blockIndex] = $temp;
            $this->sections[$sectionIndex]['blocks'] = $blocks;
        }
    }

    public function moveBlockDown($sectionIndex, $blockIndex)
    {
        $blocks = $this->sections[$sectionIndex]['blocks'];
        if ($blockIndex < count($blocks) - 1) {
            $temp = $blocks[$blockIndex + 1];
            $blocks[$blockIndex + 1] = $blocks[$blockIndex];
            $blocks[$blockIndex] = $temp;
            $this->sections[$sectionIndex]['blocks'] = $blocks;
        }
    }

    public function addOption($sectionIndex, $blockIndex)
    {
        $options = $this->sections[$sectionIndex]['blocks'][$blockIndex]['options'];
        $nextKey = $this->nextOptionKey($options);

        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];
        $block['options'][] = ['key' => $nextKey, 'text' => ''];
    }

    /**
     * Next free option letter, skipping letters still in use so removing an
     * option in the middle never produces a duplicate key.
     *
     * @param  array<int, array{key: string}>  $options
     */
    protected function nextOptionKey(array $options): string
    {
        $used = array_column($options, 'key');

        for ($code = ord('A'); $code <= ord('Z'); $code++) {
            $key = chr($code);
            if (!in_array($key, $used, true)) {
                return $key;
            }
        }

        // Past Z, fall back to numeric keys instead of producing invalid letters.
        $suffix = 1;
        while (in_array('A'.$suffix, $used, true)) {
            $suffix++;
        }

        return 'A'.$suffix;
    }

    public function removeOption($sectionIndex, $blockIndex, $optionIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];

        unset($block['options'][$optionIndex]);
        $block['options'] = array_values($block['options']);

        $this->repairCorrectAnswers($sectionIndex, $blockIndex);
    }

    /**
     * Drops any answer key that no longer points at an existing option, otherwise
     * the question becomes impossible to answer correctly.
     */
    protected function repairCorrectAnswers($sectionIndex, $blockIndex)
    {
        $block = &$this->sections[$sectionIndex]['blocks'][$blockIndex];
        $validKeys = array_column($block['options'] ?? [], 'key');

        if (isset($block['correct_option']) && !in_array($block['correct_option'], $validKeys, true)) {
            $block['correct_option'] = null;
        }

        if (isset($block['correct_options'])) {
            $block['correct_options'] = array_values(array_intersect($block['correct_options'], $validKeys));
        }
    }

    public function parseMarkdown()
    {
        if (empty(trim($this->markdown_content))) {
            $this->warning('El texto Markdown está vacío.');
            return;
        }

        $parsed = MarkdownImporter::parse($this->markdown_content);
        
        if (empty($parsed[0]['blocks'])) {
            $this->error('No se detectaron preguntas en el formato especificado.');
            return;
        }

        // Agregar las secciones parseadas al final
        foreach ($parsed as $sec) {
            $this->sections[] = $sec;
        }

        $this->success('Preguntas interpretadas y agregadas correctamente.');
        $this->markdown_content = ''; // Limpiar después de parsear
    }

    public function save()
    {
        $this->authorize($this->evaluation ? 'update' : 'create', $this->evaluation ?? OnlineEvaluation::class);

        $this->validate([
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:600',
            'status' => ['required', Rule::enum(EvaluationStatus::class)],
            'passing_score' => 'required|numeric|min:0|max:10',
            'max_attempts' => 'required|integer|min:0',
            'grading_method' => ['required', Rule::enum(EvaluationGradingMethod::class)],
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'subject_id' => 'required|integer|exists:subjects,id',
            'sections' => 'required|array|min:1',
        ]);

        $data = [
            'subject_id' => $this->subject_id,
            'class_session_id' => $this->class_session_id ?: null,
            'teacher_id' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'grading_method' => $this->grading_method,
            'shuffle_questions' => $this->shuffle_questions,
            'content' => EvaluationContent::normalize(['sections' => array_values($this->sections)]),
        ];

        if ($this->evaluation) {
            $this->evaluation->update($data);
            $this->success('Evaluación actualizada correctamente.');
        } else {
            OnlineEvaluation::create($data);
            $this->success('Evaluación creada correctamente.');
        }

        $this->isDirty = false;

        return $this->redirectRoute('evaluations.index');
    }
}
?>

<div>
    <x-header :title="$evaluation ? 'Editar Evaluación' : 'Nueva Evaluación'" :subtitle="$this->subject->name">
        <x-slot:actions>
            @if($isDirty)
                <span class="badge badge-warning badge-sm gap-1">
                    <x-icon name="o-pencil" class="w-3 h-3" />
                    Sin guardar
                </span>
            @endif
            <x-button label="Cancelar" link="{{ route('evaluations.index') }}" />
            <x-button label="Guardar" icon="o-check" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-header>

    {{-- Warn before discarding an unsaved evaluation. --}}
    <div x-data="{
            dirty: @entangle('isDirty'),
        }"
        x-init="window.addEventListener('beforeunload', (event) => {
            if (this.dirty) { event.preventDefault(); event.returnValue = ''; }
        })"
    ></div>

    <div class="max-w-5xl mx-auto space-y-6 pb-20">

        {{-- Tabs --}}
        <div role="tablist" class="tabs tabs-box bg-base-100 border border-base-200 p-1">
            <button role="tab"
                    class="tab gap-2 {{ $activeTab === 'data' ? 'tab-active' : '' }}"
                    wire:click="$set('activeTab', 'data')">
                <x-icon name="o-document-text" class="w-4 h-4" />
                Datos
            </button>
            <button role="tab"
                    class="tab gap-2 {{ $activeTab === 'rules' ? 'tab-active' : '' }}"
                    wire:click="$set('activeTab', 'rules')">
                <x-icon name="o-adjustments-horizontal" class="w-4 h-4" />
                Reglas
            </button>
            <button role="tab"
                    class="tab gap-2 {{ $activeTab === 'content' ? 'tab-active' : '' }}"
                    wire:click="$set('activeTab', 'content')">
                <x-icon name="o-list-bullet" class="w-4 h-4" />
                Contenido
                <span class="badge badge-neutral badge-sm">{{ $this->questionCount }}</span>
            </button>
        </div>

        @if($activeTab === 'data')
        <!-- Configuraciones Principales -->
        <x-card>
            <div class="space-y-4">
                <x-input label="Título" wire:model="title" required />
                <x-textarea label="Instrucciones" wire:model="description" rows="2" />
                <x-select 
                    label="Vincular a Clase" 
                    wire:model="class_session_id" 
                    :options="$this->classSessions" 
                    option-value="id" 
                    option-label="name" 
                    placeholder="No vincular todavía" 
                />
                <div class="grid grid-cols-2 gap-4">
                    <x-datetime label="Disponible desde" wire:model="starts_at" type="datetime-local" />
                    <x-datetime label="Disponible hasta" wire:model="ends_at" type="datetime-local" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-input label="Duración (minutos)" wire:model="duration_minutes" type="number" required />
                    <x-select
                        label="Estado"
                        wire:model="status"
                        :options="App\Enums\EvaluationStatus::options()"
                        option-value="id"
                        option-label="alias"
                    />
                </div>
            </div>
        </x-card>
        @elseif($activeTab === 'rules')
        <x-card title="Reglas de corrección">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Nota mínima para aprobar" wire:model="passing_score" type="number" step="0.1" min="0" max="10" required />
                <x-input label="Intentos permitidos" wire:model="max_attempts" type="number" min="0" required hint="0 = ilimitados" />

                <x-select
                    label="Método de calificación"
                    wire:model="grading_method"
                    :options="App\Enums\EvaluationGradingMethod::options()"
                    option-value="id"
                    option-label="alias"
                />
                <x-toggle label="Mezclar orden de preguntas" wire:model="shuffle_questions" />
            </div>

            <x-alert icon="o-calculator" class="alert-info mt-4 text-sm">
                El puntaje se normaliza sobre <strong>{{ $this->totalPoints }}</strong>
                {{ $this->totalPoints == 1 ? 'punto disponible' : 'puntos disponibles' }}
                en {{ $this->questionCount }} preguntas y se lleva a una escala de
                <strong>0 a 10</strong>. Las preguntas de desarrollo quedan pendientes
                hasta que las corrijas a mano.
            </x-alert>
        </x-card>
        @else
        <!-- Importar MD -->
        <x-card title="Importar Rápido (Markdown)">
            <p class="text-sm text-base-content/70 mb-2">
                Pegá preguntas con el formato
                <code class="text-xs">#### 1. Pregunta</code> /
                <code class="text-xs">A. Opción</code> /
                <code class="text-xs">**Respuesta correcta: A**</code>.
                Se agregan como una sección nueva, sin tocar las existentes.
            </p>
            <x-textarea wire:model="markdown_content" rows="4" placeholder="#### 1. ¿Qué es una base de datos Normalizada?
A. Una base sin duplicados
B. Una base vacía

**Respuesta correcta: A**" />
            <div class="mt-2 text-right">
                <x-button label="Importar como nueva sección" icon="o-arrow-down" wire:click="parseMarkdown" class="btn-sm btn-secondary" spinner="parseMarkdown" />
            </div>
        </x-card>

        <!-- Secciones y Bloques -->
        @error('sections')
            <x-alert icon="o-exclamation-triangle" class="alert-warning text-sm">{{ $message }}</x-alert>
        @enderror

        @foreach($sections as $sIndex => $section)
            <div class="border-2 border-base-300 rounded-xl overflow-hidden bg-base-100 shadow-sm" wire:key="section-{{ $section['id'] ?? $sIndex }}">
                <div class="bg-base-200 p-4 flex justify-between items-center">
                    <div class="flex-1 space-y-2 mr-4">
                        <x-input wire:model="sections.{{$sIndex}}.title" placeholder="Título de la Sección" class="font-bold text-lg" />
                        <x-input wire:model="sections.{{$sIndex}}.intro" placeholder="Introducción / Texto opcional para la sección" class="text-sm" />
                    </div>
                    <x-button icon="o-trash" class="btn-error btn-sm btn-circle btn-outline" wire:click="removeSection({{ $sIndex }})" />
                </div>
                
                <div class="p-4 space-y-6">
                    @foreach($section['blocks'] ?? [] as $bIndex => $block)
                        <div class="border border-base-300 rounded-lg p-4 relative bg-base-100 shadow-sm" wire:key="block-{{ $block['id'] ?? $sIndex.'-'.$bIndex }}">
                            <div class="absolute -left-3 top-4 flex flex-col gap-1">
                                <button type="button" wire:click="moveBlockUp({{$sIndex}}, {{$bIndex}})" class="btn btn-xs btn-circle bg-base-100 border-base-300 shadow"><x-icon name="o-arrow-up" class="w-3 h-3" /></button>
                                <button type="button" wire:click="moveBlockDown({{$sIndex}}, {{$bIndex}})" class="btn btn-xs btn-circle bg-base-100 border-base-300 shadow"><x-icon name="o-arrow-down" class="w-3 h-3" /></button>
                            </div>
                            
                            <div class="flex justify-between items-start mb-4 ml-2">
                                @php $blockType = App\Enums\EvaluationBlockType::tryFrom($block['type'] ?? ''); @endphp
                                <div class="badge badge-primary badge-sm gap-1">
                                    <x-icon :name="$blockType?->icon() ?? 'o-question-mark-circle'" class="w-3 h-3" />
                                    {{ $blockType?->shortLabel() ?? ($block['type'] ?? 'bloque') }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-24">
                                        <x-input wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.points" type="number" step="0.1" suffix="pts" class="input-sm" />
                                    </div>
                                    <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeBlock({{$sIndex}}, {{$bIndex}})" />
                                </div>
                            </div>
                            
                            <div class="ml-2 space-y-4">
                                @if(($block['type'] ?? null) !== 'table_design')
                                    <x-textarea wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.prompt" label="Consigna" rows="2" />
                                @endif

                                @if(($block['type'] ?? null) === 'multiple_choice')
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">Opciones</label>
                                        @foreach($block['options'] ?? [] as $oIndex => $opt)
                                            <div class="flex items-center gap-2">
                                                <input type="radio" wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_option" value="{{ $opt['key'] }}" class="radio radio-primary radio-sm" />
                                                <span class="font-mono text-sm w-4">{{ $opt['key'] }}</span>
                                                <x-input wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.options.{{$oIndex}}.text" class="flex-1 input-sm" />
                                                <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error" wire:click="removeOption({{$sIndex}}, {{$bIndex}}, {{$oIndex}})" />
                                            </div>
                                        @endforeach
                                        <x-button label="Agregar Opción" icon="o-plus" class="btn-ghost btn-sm" wire:click="addOption({{$sIndex}}, {{$bIndex}})" />
                                    </div>
                                    <x-textarea wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.explanation" label="Explicación (mostrada al corregir)" rows="1" />
                                
                                @elseif(($block['type'] ?? null) === 'true_false')
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">Respuesta Correcta</label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_option" value="true" class="radio radio-primary" />
                                                <span>Verdadero</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_option" value="false" class="radio radio-primary" />
                                                <span>Falso</span>
                                            </label>
                                        </div>
                                    </div>
                                
                                @elseif(($block['type'] ?? null) === 'checkboxes')
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">Opciones (Marca las correctas)</label>
                                        @foreach($block['options'] ?? [] as $oIndex => $opt)
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_options" value="{{ $opt['key'] }}" class="checkbox checkbox-primary checkbox-sm" />
                                                <span class="font-mono text-sm w-4">{{ $opt['key'] }}</span>
                                                <x-input wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.options.{{$oIndex}}.text" class="flex-1 input-sm" />
                                                <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error" wire:click="removeOption({{$sIndex}}, {{$bIndex}}, {{$oIndex}})" />
                                            </div>
                                        @endforeach
                                        <x-button label="Agregar Opción" icon="o-plus" class="btn-ghost btn-sm" wire:click="addOption({{$sIndex}}, {{$bIndex}})" />
                                    </div>
                                    
                                @elseif(($block['type'] ?? null) === 'matching')
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">
                                            Pares (término → concepto)
                                        </label>
                                        @foreach($block['pairs'] ?? [] as $pIndex => $pair)
                                            <div class="flex items-center gap-2" wire:key="pair-{{ $pIndex }}">
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.pairs.{{ $pIndex }}.prompt"
                                                    placeholder="Término"
                                                    class="flex-1 input-sm"
                                                />
                                                <x-icon name="o-arrow-right" class="w-4 h-4 text-base-content/50 shrink-0" />
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.pairs.{{ $pIndex }}.answer"
                                                    placeholder="Concepto"
                                                    class="flex-1 input-sm"
                                                />
                                                <x-button
                                                    icon="o-x-mark"
                                                    class="btn-ghost btn-xs text-error shrink-0"
                                                    wire:click="removePair({{ $sIndex }}, {{ $bIndex }}, {{ $pIndex }})"
                                                    tooltip="Quitar par"
                                                />
                                            </div>
                                        @endforeach
                                        <x-button
                                            label="Agregar Par"
                                            icon="o-plus"
                                            class="btn-ghost btn-sm"
                                            wire:click="addPair({{ $sIndex }}, {{ $bIndex }})"
                                        />
                                        <p class="text-xs text-base-content/60">
                                            Las respuestas se mezclan al azar en la evaluación; el alumno
                                            decide a qué término corresponde cada concepto.
                                        </p>
                                    </div>

                                @elseif(($block['type'] ?? null) === 'short_text')
                                    <x-input wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_answer" label="Respuesta correcta esperada (opcional)" hint="Si lo dejás vacío, la corrects a mano." />

                                @elseif(($block['type'] ?? null) === 'table_design')
                                    {{-- ATTRIBUTES --}}
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">Atributos disponibles</label>
                                        @foreach($block['attributes'] ?? [] as $aIndex => $attribute)
                                            <div class="flex items-center gap-2" wire:key="attr-{{ $aIndex }}">
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.attributes.{{ $aIndex }}"
                                                    placeholder="Nombre del atributo"
                                                    class="flex-1 input-sm"
                                                />
                                                <x-button
                                                    icon="o-x-mark"
                                                    class="btn-ghost btn-xs text-error shrink-0"
                                                    wire:click="removeAttribute({{ $sIndex }}, {{ $bIndex }}, {{ $aIndex }})"
                                                    tooltip="Quitar"
                                                />
                                            </div>
                                        @endforeach
                                        <x-button
                                            label="Agregar Atributo"
                                            icon="o-plus"
                                            class="btn-ghost btn-sm"
                                            wire:click="addAttribute({{ $sIndex }}, {{ $bIndex }})"
                                        />
                                    </div>

                                    {{-- TABLES --}}
                                    <div class="space-y-2">
                                        <label class="text-sm font-semibold">Tablas que debe armar el alumno</label>
                                        @foreach($block['tables'] ?? [] as $tIndex => $table)
                                            <div class="flex items-center gap-2" wire:key="schema-table-{{ $tIndex }}">
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.tables.{{ $tIndex }}.name"
                                                    placeholder="NOMBRE_TABLA"
                                                    class="flex-1 input-sm font-mono"
                                                />
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.tables.{{ $tIndex }}.rows"
                                                    type="number"
                                                    min="1"
                                                    max="12"
                                                    suffix="filas"
                                                    class="w-28 input-sm shrink-0"
                                                />
                                                <x-button
                                                    icon="o-x-mark"
                                                    class="btn-ghost btn-xs text-error shrink-0"
                                                    wire:click="removeSchemaTable({{ $sIndex }}, {{ $bIndex }}, {{ $tIndex }})"
                                                    tooltip="Quitar tabla"
                                                />
                                            </div>
                                        @endforeach
                                        <x-button
                                            label="Agregar Tabla"
                                            icon="o-plus"
                                            class="btn-ghost btn-sm"
                                            wire:click="addSchemaTable({{ $sIndex }}, {{ $bIndex }})"
                                        />
                                    </div>

                                    {{-- ANSWER KEY: TABLES --}}
                                    <div class="space-y-3 border border-base-300 rounded-lg p-3 bg-base-200/40">
                                        <div class="flex justify-between items-center">
                                            <label class="text-sm font-semibold">
                                                Respuesta esperada · Tablas
                                            </label>
                                            <x-button
                                                label="Agregar"
                                                icon="o-plus"
                                                class="btn-ghost btn-xs"
                                                wire:click="addKeyTable({{ $sIndex }}, {{ $bIndex }})"
                                            />
                                        </div>

                                        @foreach($block['answer_key']['tables'] ?? [] as $kIndex => $keyTable)
                                            <div class="bg-base-100 rounded-lg p-3 space-y-2 border border-base-300" wire:key="key-table-{{ $kIndex }}">
                                                <div class="flex items-center gap-2">
                                                    <x-input
                                                        wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.answer_key.tables.{{ $kIndex }}.name"
                                                        placeholder="Nombre de la tabla"
                                                        class="flex-1 input-sm font-mono"
                                                    />
                                                    <x-button
                                                        icon="o-x-mark"
                                                        class="btn-ghost btn-xs text-error shrink-0"
                                                        wire:click="removeKeyTable({{ $sIndex }}, {{ $bIndex }}, {{ $kIndex }})"
                                                        tooltip="Quitar"
                                                    />
                                                </div>

                                                <div class="text-xs text-base-content/60">
                                                    Marcá la <strong>clave primaria</strong> y las
                                                    <strong>foráneas</strong> de cada columna.
                                                </div>

                                                @foreach($keyTable['columns'] ?? [] as $cIndex => $column)
                                                    <div class="flex items-center gap-2" wire:key="key-column-{{ $kIndex }}-{{ $cIndex }}">
                                                        <x-input
                                                            wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.answer_key.tables.{{ $kIndex }}.columns.{{ $cIndex }}"
                                                            placeholder="Columna"
                                                            class="flex-1 input-sm"
                                                        />
                                                        <button
                                                            type="button"
                                                            wire:click="setKeyPrimaryKey({{ $sIndex }}, {{ $bIndex }}, {{ $kIndex }}, @js($column))"
                                                            class="btn btn-xs shrink-0 {{ ($keyTable['pk'] ?? null) === $column ? 'btn-primary' : 'btn-outline' }}"
                                                            title="Clave primaria">
                                                            PK
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="toggleKeyForeignKey({{ $sIndex }}, {{ $bIndex }}, {{ $kIndex }}, @js($column))"
                                                            class="btn btn-xs shrink-0 {{ in_array($column, $keyTable['fks'] ?? [], true) ? 'btn-info' : 'btn-outline' }}"
                                                            title="Clave foránea">
                                                            FK
                                                        </button>
                                                        <x-button
                                                            icon="o-x-mark"
                                                            class="btn-ghost btn-xs text-error shrink-0"
                                                            wire:click="removeKeyColumn({{ $sIndex }}, {{ $bIndex }}, {{ $kIndex }}, {{ $cIndex }})"
                                                        />
                                                    </div>
                                                @endforeach

                                                <x-button
                                                    label="Agregar Columna"
                                                    icon="o-plus"
                                                    class="btn-ghost btn-xs"
                                                    wire:click="addKeyColumn({{ $sIndex }}, {{ $bIndex }}, {{ $kIndex }})"
                                                />
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- ANSWER KEY: RELATIONS --}}
                                    <div class="space-y-2 border border-base-300 rounded-lg p-3 bg-base-200/40">
                                        <div class="flex justify-between items-center">
                                            <label class="text-sm font-semibold">
                                                Respuesta esperada · Relaciones
                                            </label>
                                            <x-button
                                                label="Agregar"
                                                icon="o-plus"
                                                class="btn-ghost btn-xs"
                                                wire:click="addKeyRelation({{ $sIndex }}, {{ $bIndex }})"
                                            />
                                        </div>

                                        @foreach($block['answer_key']['relations'] ?? [] as $rIndex => $relation)
                                            <div class="flex flex-wrap items-center gap-2" wire:key="key-relation-{{ $rIndex }}">
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.answer_key.relations.{{ $rIndex }}.from"
                                                    placeholder="Origen"
                                                    class="flex-1 input-sm font-mono min-w-24"
                                                />
                                                <x-icon name="o-arrow-right" class="w-4 h-4 text-base-content/50 shrink-0" />
                                                <x-input
                                                    wire:model="sections.{{ $sIndex }}.blocks.{{ $bIndex }}.answer_key.relations.{{ $rIndex }}.to"
                                                    placeholder="Destino"
                                                    class="flex-1 input-sm font-mono min-w-24"
                                                />
                                                <div class="join shrink-0">
                                                    @foreach(['1:1', '1:N', 'N:N'] as $type)
                                                        <button
                                                            type="button"
                                                            wire:click="toggleKeyRelationType({{ $sIndex }}, {{ $bIndex }}, {{ $rIndex }}, @js($type))"
                                                            class="btn btn-xs join-item {{ ($relation['type'] ?? '1:N') === $type ? 'btn-primary' : 'btn-outline' }}">
                                                            {{ $type }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                                <x-button
                                                    icon="o-x-mark"
                                                    class="btn-ghost btn-xs text-error shrink-0"
                                                    wire:click="removeKeyRelation({{ $sIndex }}, {{ $bIndex }}, {{ $rIndex }})"
                                                />
                                            </div>
                                        @endforeach
                                    </div>

                                    <x-alert icon="o-light-bulb" class="alert-info text-sm">
                                        Sin columnas marcadas en la clave, el ejercicio no se puede
                                        corregir solo y queda pendiente de revisión del docente.
                                    </x-alert>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap gap-2 justify-center mt-4">
                        @foreach(App\Enums\EvaluationBlockType::cases() as $type)
                            <x-button
                                :label="$type->shortLabel()"
                                :icon="$type->icon()"
                                class="btn-outline btn-sm"
                                wire:click="addBlock({{ $sIndex }}, '{{ $type->value }}')"
                            />
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="text-center">
            <x-button label="Agregar Sección" icon="o-plus" class="btn-secondary btn-wide" wire:click="addSection" />
        </div>
        @endif
    </div>
</div>
