<div>
    <x-card title="Importar Usuarios" shadow separator>
        <x-form wire:submit="importBulk" no-separator>
            <x-file label="Archivo CSV" accept="text/csv" type="file" wire:model="csvfile" />
            <x-slot:actions>
                <x-button label="Importar" class="btn-primary" type="submit" spinner="importBulk" />
            </x-slot:actions>
        </x-form>

    </x-card>
    {{-- show log --}}
    <table class="table w-full striped">
        <thead>
            <tr>
                <th>Log</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($log as $item)
                <tr>
                    <td class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $item }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>