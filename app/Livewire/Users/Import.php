<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Import extends Component
{
    use WithFileUploads;

    public $log = [];

    #[Validate('required|mimes:csv,txt')]
    public $csvfile;

    public function importBulk()
    {
        $this->log = [];
        try {
            $this->validate(); // Validar el archivo usando la validación definida en la propiedad $csvfile
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->log[] = 'Error de validación: '.$e->getMessage();

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
            if (Storage::disk('local')->exists('csv/users.csv')) {
                $csvData = Storage::disk('local')->get('csv/users.csv');
                $rows = array_map('str_getcsv', explode("\n", $csvData));
                $header = array_shift($rows);
                if ($header) {
                    foreach ($rows as $row) {
                        if (count($row) !== count($header)) {
                            continue;
                        }
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
                            $this->log[] = 'Usuario '.$row['email'].' importado correctamente.';

                        } catch (\Illuminate\Database\QueryException $exception) {
                            $this->log[] = 'Error al importar usuario '.$row['email'].': '.$exception->getMessage();
                        }
                    }
                }
                $this->log[] = 'Proceso Terminado';
            } else {
                $this->log[] = 'Error: Archivo no encontrado.';
            }
        } catch (\Exception $e) {
            $this->log[] = 'Error general en la importación: '.$e->getMessage();
        }

        ini_set('max_execution_time', 60);
    }

    public function render()
    {
        return view('livewire.users.import');
    }
}
