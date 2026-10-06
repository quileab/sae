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

    public function mount($id = null)
    {
        if ($id) {
            $this->evaluation = OnlineEvaluation::findOrFail($id);
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
        unset($this->sections[$sectionIndex]['blocks'][$blockIndex]);
        $this->sections[$sectionIndex]['blocks'] = array_values($this->sections[$sectionIndex]['blocks']);
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

        $this->sections[$sectionIndex]['blocks'][$blockIndex]['options'][] = ['key' => $nextKey, 'text' => ''];
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
        unset($this->sections[$sectionIndex]['blocks'][$blockIndex]['options'][$optionIndex]);
        $this->sections[$sectionIndex]['blocks'][$blockIndex]['options'] = array_values($this->sections[$sectionIndex]['blocks'][$blockIndex]['options']);

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
        $this->validate([
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:600',
            'status' => ['required', Rule::enum(EvaluationStatus::class)],
            'passing_score' => 'required|numeric|min:0|max:10',
            'max_attempts' => 'required|integer|min:0',
            'grading_method' => ['required', Rule::enum(EvaluationGradingMethod::class)],
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
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

        return $this->redirectRoute('evaluations.index');
    }
}
?>

<div>
    <x-header :title="$evaluation ? 'Editar Evaluación' : 'Nueva Evaluación'" :subtitle="$this->subject->name">
        <x-slot:actions>
            <x-button label="Cancelar" link="{{ route('evaluations.index') }}" />
            <x-button label="Guardar" icon="o-check" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-header>

    <div class="max-w-4xl mx-auto space-y-6 pb-20">
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

        <x-card title="Configuración Avanzada" class="bg-base-200/50">
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
        </x-card>

        <!-- Importar MD -->
        <x-card title="Importar Rápido (Markdown)">
            <x-textarea wire:model="markdown_content" rows="3" placeholder="Pegá preguntas aquí..." />
            <div class="mt-2 text-right">
                <x-button label="Importar a una nueva sección" icon="o-arrow-down" wire:click="parseMarkdown" class="btn-sm btn-secondary" />
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
                                    <div class="alert alert-info py-2">
                                        <x-icon name="o-information-circle" />
                                        <span>Editor avanzado de pares. (Por simplicidad en la demo, se puede editar vía JSON o usar una UI básica de pares).</span>
                                    </div>
                                
                                @elseif(($block['type'] ?? null) === 'short_text')
                                    <x-input wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.correct_answer" label="Respuesta correcta esperada (opcional)" />
                                
                                @elseif(($block['type'] ?? null) === 'table_design')
                                    <div class="alert alert-info py-2">
                                        <x-icon name="o-information-circle" />
                                        <span>El diseño relacional se edita con código o UI avanzada.</span>
                                    </div>
                                    <x-textarea wire:model="sections.{{$sIndex}}.blocks.{{$bIndex}}.prompt" label="Consigna del Ejercicio" rows="2" />
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
    </div>
</div>
