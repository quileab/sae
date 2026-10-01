<?php

namespace App\Livewire\PreEnrollments;

use App\Models\PreEnrollment;
use App\Services\AcademicCycle;
use App\Services\PreEnrollmentPdfService;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination;
    use Toast;

    public $statusFilter = '';

    public $cycleFilter;

    public bool $drawer = false;

    public ?PreEnrollment $selected = null;

    public function mount(): void
    {
        $this->cycleFilter = AcademicCycle::enrollmentCycle();
    }

    public function openDrawer(int $id): void
    {
        $this->selected = PreEnrollment::with('career')->findOrFail($id);
        $this->drawer = true;
    }

    public function updateStatus(int $id, string $status): void
    {
        $pre = PreEnrollment::findOrFail($id);
        $pre->update(['status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        if ($status === 'validated') {
            app(PreEnrollmentPdfService::class)->generate($pre->fresh());
        }
        
        $this->drawer = false;
        $this->success('Estado actualizado correctamente a: ' . $status);
    }

    public function delete(int $id): void
    {
        $pre = PreEnrollment::findOrFail($id);
        $pre->delete();
        $this->drawer = false;
        $this->selected = null;
        $this->warning('Preinscripción eliminada permanentemente.');
    }

    public function exportCsv()
    {
        $query = PreEnrollment::with('career')->latest();
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->cycleFilter) {
            $query->where('cycle_id', $this->cycleFilter);
        }

        $records = $query->get();

        $csvFileName = 'preinscripciones_' . date('Ymd_His') . '.csv';
        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($records) {
            echo "\xEF\xBB\xBF"; // BOM for Excel
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Apellido y Nombre', 'Tipo Doc', 'Núm Doc', 'Escuela', 'Carrera', 'Email', 'Teléfono', 'Estado',
                'Padre/Madre 1', 'Email P1', 'Tel P1',
                'Padre/Madre 2', 'Email P2', 'Tel P2',
                'Tutor', 'Email Tutor', 'Tel Tutor'
            ]);

            foreach ($records as $row) {
                $name = ($row->payload['lname'] ?? '') . ', ' . ($row->payload['fname'] ?? '');
                $escuela = $row->payload['escuela'] ?? '-';
                
                $p1Name = trim(($row->payload['lname1'] ?? '') . ', ' . ($row->payload['fname1'] ?? ''), ', ');
                $p2Name = trim(($row->payload['lname2'] ?? '') . ', ' . ($row->payload['fname2'] ?? ''), ', ');
                $tutorName = trim(($row->payload['lname3'] ?? '') . ', ' . ($row->payload['fname3'] ?? ''), ', ');

                fputcsv($file, [
                    $name,
                    $row->doc_type,
                    $row->doc_number,
                    $escuela,
                    $row->career->name ?? '-',
                    $row->email,
                    $row->phone ?? '-',
                    $row->status,
                    $p1Name,
                    $row->payload['email1'] ?? '',
                    $row->payload['phone1'] ?? '',
                    $p2Name,
                    $row->payload['email2'] ?? '',
                    $row->payload['phone2'] ?? '',
                    $tutorName,
                    $row->payload['email3'] ?? '',
                    $row->payload['phone3'] ?? ''
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }

    public function render()
    {
        $query = PreEnrollment::with('career')->latest();
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->cycleFilter) {
            $query->where('cycle_id', $this->cycleFilter);
        }

        $baseQuery = PreEnrollment::query();
        if ($this->cycleFilter) {
            $baseQuery->where('cycle_id', $this->cycleFilter);
        }
        $rawCounts = $baseQuery->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusMap = [
            'submitted' => 'Enviado',
            'validated' => 'Validado',
            'waitlisted' => 'En espera',
            'observed' => 'Observado',
            'rejected' => 'Rechazado',
            'enrolled' => 'Matriculado'
        ];

        $statusCounts = [];
        foreach ($statusMap as $key => $label) {
            $statusCounts[$label] = $rawCounts[$key] ?? 0;
        }

        $totalCount = array_sum($statusCounts);
        $currentLabel = $this->statusFilter ? ($statusMap[$this->statusFilter] ?? 'Todos') : 'Todos';
        $currentCount = $this->statusFilter ? ($statusCounts[$currentLabel] ?? 0) : $totalCount;

        return view('livewire.pre-enrollments.index', [
            'preEnrollments' => $query->paginate(100),
            'statusCounts' => $statusCounts,
            'totalCount' => $totalCount,
            'currentLabel' => $currentLabel,
            'currentCount' => $currentCount,
        ]);
    }
}
