<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Import extends Component
{
    use WithFileUploads;

    public $log = [];

    public $stats = [
        'success' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    public $isImporting = false;

    public $currentRow = 0;

    public $totalRows = 0;

    public $filePath = null;

    public $headers = [];

    #[Validate('required|mimes:csv,txt|max:10240')]
    public $csvfile;

    public function downloadExample()
    {
        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['id', 'lastname', 'firstname', 'phone', 'email', 'career_id']);
            fputcsv($file, ['12345678', 'Pérez', 'Juan', '1122334455', 'juan.perez@example.com', '1']);
            fputcsv($file, ['87654321', 'García', 'María', '5544332211', 'maria.garcia@example.com', '2']);
            fclose($file);
        };

        return response()->streamDownload($callback, 'ejemplo_usuarios.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function startImport()
    {
        $this->reset(['log', 'stats', 'currentRow', 'totalRows']);
        $this->validate();

        // Guardar archivo temporalmente
        $name = 'import_'.auth()->id().'.csv';
        $this->filePath = $this->csvfile->storeAs('temp', $name, 'local');
        $fullPath = storage_path('app/private/'.$this->filePath);

        $file = fopen($fullPath, 'r');
        $this->headers = array_map('trim', fgetcsv($file));

        // Contar filas totales
        $this->totalRows = 0;
        while (fgetcsv($file)) {
            $this->totalRows++;
        }
        fclose($file);

        $this->isImporting = true;
        $this->log[] = "Archivo cargado. Total de registros detectados: {$this->totalRows}";

        // Iniciar primer lote
        $this->importNextBatch();
    }

    public function importNextBatch()
    {
        if (! $this->isImporting) {
            return;
        }

        $batchSize = 25; // Procesar de a 25 para evitar timeouts
        $fullPath = storage_path('app/private/'.$this->filePath);
        $file = fopen($fullPath, 'r');

        // Saltar cabecera y filas ya procesadas
        fgetcsv($file);
        for ($i = 0; $i < $this->currentRow; $i++) {
            fgetcsv($file);
        }

        $processedInThisBatch = 0;
        while ($processedInThisBatch < $batchSize && ($row = fgetcsv($file)) !== false) {
            $this->currentRow++;
            $processedInThisBatch++;

            if (count($row) !== count($this->headers)) {
                $this->log[] = "Fila {$this->currentRow}: Omitida (columnas incorrectas).";
                $this->stats['skipped']++;

                continue;
            }

            $data = array_combine($this->headers, $row);
            $data = array_map('trim', $data);

            $validator = Validator::make($data, [
                'id' => 'required|numeric',
                'email' => 'required|email',
                'firstname' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'career_id' => 'required|exists:careers,id',
            ]);

            if ($validator->fails()) {
                $this->log[] = "ID {$data['id']}: Error - ".implode(', ', $validator->errors()->all());
                $this->stats['errors']++;

                continue;
            }

            try {
                DB::transaction(function () use ($data) {
                    $user = User::updateOrCreate(
                        ['id' => $data['id']],
                        [
                            'name' => $data['id'],
                            'lastname' => $data['lastname'],
                            'firstname' => $data['firstname'],
                            'phone' => $data['phone'] ?? null,
                            'enabled' => true,
                            'email' => $data['email'],
                            'password' => Hash::make($data['id']),
                            'role' => 'student',
                        ]
                    );
                    $user->careers()->syncWithoutDetaching([$data['career_id']]);
                });
                $this->stats['success']++;
            } catch (\Exception $e) {
                $this->log[] = "ID {$data['id']}: Excepción - ".$e->getMessage();
                $this->stats['errors']++;
            }
        }

        fclose($file);

        if ($this->currentRow >= $this->totalRows) {
            $this->isImporting = false;
            $this->log[] = 'Importación completada.';
            Storage::disk('local')->delete($this->filePath);
        } else {
            // Pedir el siguiente lote automáticamente desde el navegador
            $this->dispatch('next-batch');
        }
    }

    public function render()
    {
        return view('livewire.users.import');
    }
}
