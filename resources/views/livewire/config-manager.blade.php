<div class="max-w-4xl mx-auto space-y-6 p-4">
    <x-header title="Configuraciones del Sistema" subtitle="Ajustes técnicos y parámetros de integración" separator />

    <x-card class="shadow-sm border border-base-300">
        <div class="divide-y divide-base-300/50">
            @foreach($data as $key => $config)
                <x-form wire:submit="saveChange({{ $key }})" id="form{{ $key }}" class="w-full py-5 first:pt-2 last:pb-2">
                    @switch($config['type'])
                        @case('text')
                            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                                <div class="flex-1 w-full">
                                    <x-input label="{{ $config['description'] }}" hint="ID: {{ $config['id'] }}" wire:model="data.{{ $key }}.value" />
                                </div>
                                <x-button label="Guardar" form="form{{ $key }}" type="submit" icon="o-check" class="btn-primary w-full md:w-auto" spinner="saveChange({{ $key }})" /> 
                            </div>
                        @break

                        @case('bool')
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <x-toggle label="{{ $config['description'] }}" wire:model="data.{{ $key }}.value" class="text-base font-medium" />
                                    <div class="text-xs text-base-content/50 mt-1 ml-14">ID: {{ $config['id'] }}</div>
                                </div>
                                <x-button label="Guardar" form="form{{ $key }}" type="submit" icon="o-check" class="btn-primary btn-sm sm:btn-md w-full sm:w-auto" spinner="saveChange({{ $key }})" /> 
                            </div>
                        @break

                        @default
                            <div class="p-4 bg-error/10 text-error rounded-lg border border-error/20 flex items-center gap-2">
                                <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                                <span>Error: Tipo de configuración no soportado ({{ $config['type'] }})</span>
                            </div>
                    @endswitch
                </x-form>
            @endforeach
        </div>
    </x-card>
</div>