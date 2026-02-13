<div>
    <x-card title="Importar Usuarios" shadow separator>
        <x-form wire:submit.prevent="startImport" no-separator>
            <div class="flex items-end w-full gap-4">
                <div class="grow">
                    <x-file label="Archivo CSV" accept="text/csv" type="file" wire:model="csvfile" class="w-full" />
                </div>
                @if(!$isImporting)
                    <x-button label="Iniciar Importación" class="btn-primary" type="submit" spinner="startImport" />
                @else
                    <x-button label="Procesando..." class="btn-disabled" loading />
                @endif
            </div>
            
            @if($isImporting || $totalRows > 0)
                <div class="mt-4" wire:key="import-progress">
                    <div class="flex justify-between mb-1 text-xs">
                        <span>Progreso: {{ $currentRow }} / {{ $totalRows }}</span>
                        <span>{{ round(($currentRow / max($totalRows, 1)) * 100) }}%</span>
                    </div>
                    <progress class="progress progress-primary w-full" value="{{ $currentRow }}" max="{{ $totalRows }}"></progress>
                </div>
            @endif

            <div class="flex gap-4 mt-4" wire:key="import-stats">
                <x-stat title="Exitosos" value="{{ $stats['success'] }}" icon="o-check-circle" class="text-success" />
                <x-stat title="Errores" value="{{ $stats['errors'] }}" icon="o-exclamation-circle" class="text-error" />
                <x-stat title="Omitidos" value="{{ $stats['skipped'] }}" icon="o-minus-circle" class="text-warning" />
            </div>

            <x-slot:actions>
                <x-button label="Descargar Ejemplo" class="btn-ghost" wire:click="downloadExample" icon="o-arrow-down-tray" />
            </x-slot:actions>
        </x-form>
    </x-card>

    <div class="mt-4" wire:key="import-log-container">
        <x-card title="Registro de Actividad (Últimos eventos)" shadow>
            <div class="overflow-x-auto h-96">
                <table class="table table-xs w-full striped">
                    <thead>
                        <tr>
                            <th>Evento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse(array_slice($log, -100)) as $index => $item)
                            <tr wire:key="log-row-{{ $index }}">
                                <td class="font-mono text-xs {{ (str_contains($item, 'Error') || str_contains($item, 'Excepción')) ? 'text-error' : (str_contains($item, 'completada') || str_contains($item, 'detectados') ? 'text-info' : 'text-success') }}">
                                    {{ $item }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @script
    <script>
        $wire.on('next-batch', () => {
            setTimeout(() => {
                $wire.importNextBatch();
            }, 100);
        });
    </script>
    @endscript
</div>
