<div>
    <!-- HEADER -->
    <x-card title="Materia" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive 
            icon="o-ellipsis-vertical"
            class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
    <x-form wire:submit="save" no-separator>    
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