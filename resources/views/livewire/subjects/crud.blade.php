<div>
    <x-card title="Materia" shadow separator>
    <x-form wire:submit="save" no-separator>    
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <x-input label="ID" type="number" wire:model="data.id" />
            <x-select label="Carrera" icon="o-academic-cap" :options="$careers" wire:model.live="data.career_id" />
        </div>
        <x-input label="Nombre de la Materia" type="text" wire:model="data.name" />
        <x-input label="Correlatividades" type="text" wire:model="data.prerequisite" readonly />
        
        @if($subjects && count($subjects) > 0)
            <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2">
                <div class="p-4 rounded-lg bg-base-200">
                    <div class="mb-2 font-bold text-center">Para Cursar</div>
                    <div class="overflow-y-auto max-h-64">
                        @foreach ($subjects as $subject)
                            <div 
                                @class([
                                    'border border-white/10 px-2 py-1 cursor-pointer hover:bg-base-300 transition-colors',
                                    'bg-lime-500/50 text-white font-bold' => in_array($subject->id, $subjectsToStudy),
                                ])                        
                                wire:click="toggleSubjectTo('study',{{ $subject->id }})"
                            >
                                <small>{{ $subject->id }} » {{ $subject->name }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="p-4 rounded-lg bg-base-200">
                    <div class="mb-2 font-bold text-center">Para Exámenes</div>
                    <div class="overflow-y-auto max-h-64">
                        @foreach ($subjects as $subject)
                            <div 
                                @class([
                                    'border border-white/10 px-2 py-1 cursor-pointer hover:bg-base-300 transition-colors',
                                    'bg-lime-500/50 text-white font-bold' => in_array($subject->id, $subjectsToExam),
                                ])                        
                                wire:click="toggleSubjectTo('exam',{{ $subject->id }})"
                            >
                                <small>{{ $subject->id }} » {{ $subject->name }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if($data['id'])
                <x-dropdown label="ELIMINAR" icon="o-trash" class="btn-error btn-outline">
                    <x-menu-item title="Confirmar eliminación" icon="o-trash" wire:click="delete" spinner class="text-error" />
                </x-dropdown>
            @endif
            <x-button label="Cancelar" link="/subjects" />
            <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form>
    </x-card>
</div>