<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public array $data = [
        'id' => null,
        'career_id' => '',
        'name' => '',
        'prerequisite' => '',
    ];

    public $careers;
    public $subjects;
    public $drawer = false;

    public $subjectsToStudy = [];
    public $subjectsToExam = [];

    public function mount($id=null) {
        if($id===null) {
            $id = session('subject_id');
        }

        if ($id!==null) {
            $this->data = \App\Models\Subject::find($id)->toArray();
        }

        $this->careers=\App\Models\Career::all();
        $this->subjects=\App\Models\Subject::where('career_id', $this->data['career_id'])->get();
        $this->createPrequisite();
    }

    public function save() {
        $subject = \App\Models\Subject::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Mareria guardada.');
        return redirect('/subjects');
    }

    public function delete() {
        $item = \App\Models\Subject::find($this->data['id']);
        $item->delete();
        $this->success('Registro Eliminado.');
        return redirect('/subjects');
    }

    private function createPrequisite() {
        // if prerequisite not empty, and array is empty, create arrays
        if (!empty($this->data['prerequisite']) && empty($this->subjectsToStudy) && empty($this->subjectsToExam)) {
            // split prerequisite study/exam by '/'
            $prerequisite = explode('/', $this->data['prerequisite']);

            // split prerequisite study/exam by ' '
            $this->subjectsToStudy = explode(' ', $prerequisite[0]);
            $this->subjectsToExam = explode(' ', $prerequisite[1]);            
        }

        // order by value subjectsStudy and subjectsToExam
            sort($this->subjectsToStudy);
            sort($this->subjectsToExam);
        // Create prequisite string from array of subjects
        $this->data['prerequisite'] = 
            implode(' ', $this->subjectsToStudy).'/'.implode(' ', $this->subjectsToExam);
        
    }

    public function toggleSubjectTo($to, $subject_id) {
        if ($to == 'study') {
            if (in_array($subject_id, $this->subjectsToStudy)) {
                $this->subjectsToStudy = array_diff($this->subjectsToStudy, [$subject_id]);
            } else {
                $this->subjectsToStudy[] = $subject_id;
            }
        } else {
            if (in_array($subject_id, $this->subjectsToExam)) {
                $this->subjectsToExam = array_diff($this->subjectsToExam, [$subject_id]);
            } else {
                $this->subjectsToExam[] = $subject_id;
            }
        }
        $this->createPrequisite();
    }

}; ?>

<div>
    <!-- HEADER -->
    <x-card title="Materia" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive 
            icon="o-ellipsis-vertical"
            class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
    <x-form wire:submit="save">    
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <x-input label="ID" type="number" wire:model="data.id" />
            <x-select label="Carrera" icon="o-academic-cap" :options="$careers" wire:model.lazy="data.career_id" />
        </div>
        <x-input label="Carrera" type="text" wire:model="data.name" />
        <x-input label="Correlatividades" type="text" wire:model="data.prerequisite" readonly />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                Para Cursar
                <!-- Listado de materias -->
                @foreach ($subjects as $subject)
                    <div 
                        @class([
                            'border border-white/10 px-2 cursor-pointer',
                            'bg-lime-500/50 text-white' => in_array($subject->id, $subjectsToStudy),
                        ])                        
                        wire:click="toggleSubjectTo('study',{{ $subject->id }})"
                    >
                        <small>{{ $subject->id }} » {{ $subject->name }}</small>
                    </div>

                @endforeach
            </div>
            <div>
                Para Exámenes
                <!-- Listado de materias -->
                @foreach ($subjects as $subject)
                    <div 
                        @class([
                            'border border-white/10 px-2 cursor-pointer',
                            'bg-lime-500/50 text-white' => in_array($subject->id, $subjectsToExam),
                        ])                        
                        wire:click="toggleSubjectTo('exam',{{ $subject->id }})"
                    >
                        <small>{{ $subject->id }} » {{ $subject->name }}</small>
                    </div>
                @endforeach
            </div>
            
        </div>

        <x-slot:actions>
            <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form>
    </x-card>

    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button 
        separator with-close-button close-on-escape
        class="lg:w-1/3">

        <x-slot:actions>
            <x-dropdown label="ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash" class="bg-error text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>