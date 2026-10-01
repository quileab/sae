<div class="p-4">
    <h1 class="text-2xl font-bold mb-4">Materiales para inscriptos — {{ $this->cycle }}</h1>
    @if (session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    <div class="card bg-base-100 border p-4 mb-4">
        <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
            <x-input label="Título" wire:model="title" placeholder="Normas de convivencia" />
            <x-select label="Carrera (todas si vacío)" wire:model="career_id" :options="$careers" option-value="id" option-label="name" placeholder="Todas" />
            <x-input label="Ciclo" wire:model="cycle" type="number" />
            <x-file label="Archivo" wire:model="file" accept=".pdf,.doc,.docx,.zip" />
            <x-button label="Subir" type="submit" class="btn-primary" spinner="save" />
        </form>
    </div>
    <x-table :headers="[['key'=>'title','label'=>'Título'],['key'=>'career','label'=>'Carrera'],['key'=>'file','label'=>'Archivo'],['key'=>'actions','label'=>'']]" :rows="$materials">
        @scope('cell_career', $row) {{ $row->career->name ?? 'Todas' }} @endscope
        @scope('cell_file', $row) <a href="{{ Storage::disk('public')->url($row->file_path) }}" target="_blank" class="link">{{ $row->original_name }}</a> @endscope
        @scope('actions', $row) <x-button icon="o-trash" wire:click="delete({{ $row->id }})" wire:confirm="¿Eliminar?" class="btn-xs btn-ghost text-error" /> @endscope
    </x-table>
</div>
