<div>
    <!-- HEADER -->
    <x-header title="Inscripciones {{ $user->name }}" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        {{-- <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions> --}}
    </x-header>
    {{-- if return with message --}}
    @if (session()->has('success'))
        <x-alert icon="o-information-circle" title="{{ session('success') }}" class="alert-success" dismissible />
    @endif

    <!-- TABLE  -->
    <x-card>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 sticky top-0 z-20 backdrop-blur-md
        pb-1 border-b border-black/20 dark:border-white/20">
            <x-select wire:model.lazy="inscription_id" label="Inscripciones a" :options="$inscriptions"
                option-value="id" option-label="description" />
            <x-select wire:model.lazy="career_id" label="Carrera" :options="$careers" />
            @if($user->enabled)
                <div class="grid grid-cols-2 gap-2">
                    @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                        <x-select label="Tipo" wire:model.lazy="type" :options="$types" />
                    @else
                        <x-button label="Confirmar" @click="$wire.drawer = true" responsive icon="o-bars-3"
                            class="btn-secondary mt-7" />
                    @endif
                    <x-button label="Guardar" icon="o-check" class="btn-primary mt-7" wire:click="save" />
                </div>
            @else
                <x-alert title="Se ha encontrado un error. Verifique con Tesorería	" icon="o-exclamation-triangle"
                    class="alert-error" shadow />
            @endif
        </div>
        <div class="z-10">
            <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
                @scope('cell_value', $item, $user, $subjects, $type)
                @if($user->hasAnyRole(['admin', 'principal', 'administrative']))
                    <x-input icon="o-cube" :key="$item->id" wire:model="subjects.{{ $item->id }}.value" />
                @else
                                @php
                                    $values = array_map(function ($item) {
                                        return ['id' => $item, 'name' => $item];
                                    }, explode(',', $subjects[$item->id]['value']));
                                @endphp

                                {{-- if type csv-1 add single to x-choices --}}
                                @if($type == 'csv-1')
                                    <x-choices wire:model="subjects.{{ $item->id }}.selected" :options="$values" :key="uniqid()"
                                        class="w-full" single />
                                @else
                                    <x-choices wire:model="subjects.{{ $item->id }}.selected" :options="$values" :key="uniqid()"
                                        class="w-full" />
                                @endif

                @endif
                @endscope
            </x-table>
        </div>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">

        <div class="grid grid-cols-2 gap-2">
            <x-button label="Previsualizar" icon="o-eye" class="btn-warning"
                link="/inscriptionsPDF/{{ $user->id }}/{{ $career_id }}/{{ $inscription_id }}" external />
            <x-button label="Enviar" icon="o-paper-airplane" class="btn-success"
                link="/inscriptionsSavePDF/{{ $user->id }}/{{ $career_id }}/{{ $inscription_id }}" />
        </div>

    </x-drawer>

</div>