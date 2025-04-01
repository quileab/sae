<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithFileUploads;

    public $log = [];
    #[Validate('required|mimes:csv')]
    public $csvfile;

    public function importBulk()
    {
        $this->log = [];
        try {
            $this->validate(); // Validar el archivo usando la validación definida en la propiedad $csvfile
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->log[] = 'Error de validación: ' . $e->getMessage();
            return;
        }

        // store csv file as users.csv in storage csv
        $this->csvfile->storeAs('csv', 'users.csv', 'local'); // Almacena el archivo CSV en la carpeta local de almacenamiento
        // clean up the temp file
        // $this->csvfile->delete(); // Elimina el archivo temporal después de almacenarlo

        $this->log[] = 'Proceso Iniciado';


        ini_set('max_execution_time', 300);
        try {
            // verify file exists
            $csvData = Storage::get('/csv/users.csv');
            $rows = array_map('str_getcsv', explode("\n", $csvData));
            $header = array_shift($rows);
            foreach ($rows as $row) {
                $row = array_combine($header, $row);
                try {
                    $user = User::create([
                        'id' => $row['id'],
                        'name' => $row['id'],
                        'lastname' => $row['lastname'],
                        'firstname' => $row['firstname'],
                        'phone' => $row['phone'],
                        'enabled' => '1',
                        'email' => $row['email'],
                        'password' => Hash::make($row['id']), // Encriptar la contraseña
                        'role' => 'student',
                    ]);

                    $user->careers()->attach($row['career_id']); // Asignar carrera al usuario
                    $this->log[] = 'Usuario ' . $row['email'] . ' importado correctamente.';

                } catch (\Illuminate\Database\QueryException $exception) {
                    $this->log[] = 'Error al importar usuario ' . $row['email'] . ': ' . $exception->getMessage();
                }
            }
            $this->log[] = 'Proceso Terminado';
        } catch (\Exception $e) {
            $this->log[] = 'Error general en la importación: ' . $e->getMessage();
        }

        ini_set('max_execution_time', 60);
    }
};
?>

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