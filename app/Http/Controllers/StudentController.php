<?php

// *** Controlador de Laravel para importar
// *** Estudiantes
// TODO: Configurar para SAE (copia de SADMIN)

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public $errorInfo = '';

    public function importBulk(Request $request)
    {
        if (! auth()->user()->hasRole('admin')) {
            return;
        }
        $request->validate(
            [
                'file' => 'required',
            ]
        );

        ini_set('max_execution_time', 300);
        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $rows = array_map('str_getcsv', explode("\n", $csvData));
        $header = array_shift($rows);
        foreach ($rows as $row) {
            $row = array_combine($header, $row); // convierte en array asociativo con los datos de $header

            try { // user Student Creation
                $user = new User;
                $user->forceFill([
                    'id' => $row['pid'], // Assuming pid is used as ID here as well
                    'name' => $row['name'],
                    'lastname' => $row['lastname'],
                    'firstname' => $row['firstname'],
                    'phone' => $row['phone'],
                    'enabled' => $row['enabled'],
                    'email' => $row['email'],
                    'password' => Hash::make($row['pid']),
                    'role' => $request->role,
                ])->save();
            } catch (QueryException $exception) {
                // Just Continue
                // $this->errorInfo = $this->errorInfo.$exception->errorInfo;
                $user = null;
            }
            // Assign Career to User
            if ($user) {
                $user->careers()->attach($row['career']);
            }
        }
        ini_set('max_execution_time', 60);

        return redirect()->route('students')->with('success', 'Importación Exitosa');
    }
}
