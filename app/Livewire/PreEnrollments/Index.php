<?php

namespace App\Livewire\PreEnrollments;

use App\Models\Config;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentPayment;
use App\Services\AcademicCycle;
use App\Services\PreEnrollmentPdfService;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public $statusFilter = '';

    public $paymentFilter = '';

    public $cycleFilter;

    public string $search = '';

    public bool $drawer = false;

    public ?PreEnrollment $selected = null;

    // Modal de cobro
    public bool $chargeModal = false;

    public float $chargeAmount = 0.0;

    public string $chargePaymentMethod = 'cash';

    public string $chargeConcept = '';

    public string $chargeNotes = '';

    // Modal de devolución
    public bool $refundModal = false;

    public ?PreEnrollmentPayment $paymentToRefund = null;

    public string $refundReason = '';

    public string $refundPaymentMethod = 'cash';

    // Modal de arqueo de caja
    public bool $boxModal = false;

    public ?string $boxStartDate = null;

    public ?string $boxEndDate = null;

    public function mount(): void
    {
        $this->cycleFilter = AcademicCycle::enrollmentCycle();
        $this->boxStartDate = now()->format('Y-m-d');
        $this->boxEndDate = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCycleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentFilter(): void
    {
        $this->resetPage();
    }

    public function openDrawer(int $id): void
    {
        $this->selected = PreEnrollment::with(['career', 'payments'])->findOrFail($id);
        $this->drawer = true;
    }

    public function updateStatus(int $id, string $status): void
    {
        $pre = PreEnrollment::findOrFail($id);
        $pre->update(['status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        if ($status === 'validated') {
            app(PreEnrollmentPdfService::class)->generate($pre->fresh());
        }

        if ($this->selected && $this->selected->id === $id) {
            $this->selected = $pre->fresh(['career', 'payments']);
        }

        $this->success('Estado actualizado correctamente a: '.$status);
    }

    public function delete(int $id): void
    {
        $pre = PreEnrollment::findOrFail($id);
        $pre->delete();
        $this->drawer = false;
        $this->selected = null;
        $this->warning('Preinscripción eliminada permanentemente.');
    }

    public function openChargeModal(int $id): void
    {
        $this->selected = PreEnrollment::with(['career', 'payments'])->findOrFail($id);
        $defaultFee = (float) Config::get('pre_enrollment_fee', 0);
        $this->chargeAmount = $defaultFee;
        $this->chargePaymentMethod = 'cash';
        $this->chargeConcept = 'Cobro de Preinscripción Ciclo '.($this->selected->cycle_id ?? $this->cycleFilter);
        $this->chargeNotes = '';
        $this->drawer = false;
        $this->chargeModal = true;
    }

    public function processCharge(): void
    {
        if (! $this->selected) {
            return;
        }

        $this->validate([
            'chargeAmount' => 'required|numeric|min:0.01',
            'chargePaymentMethod' => 'required|string',
            'chargeConcept' => 'required|string|max:255',
        ]);

        $payerName = trim(($this->selected->payload['lname'] ?? '').', '.($this->selected->payload['fname'] ?? ''));
        if (empty($payerName)) {
            $payerName = 'Aspirante DNI '.$this->selected->doc_number;
        }

        $payment = PreEnrollmentPayment::create([
            'pre_enrollment_id' => $this->selected->id,
            'cycle_id' => $this->selected->cycle_id,
            'doc_type' => $this->selected->doc_type,
            'doc_number' => $this->selected->doc_number,
            'payer_name' => $payerName,
            'career_name' => $this->selected->career?->name ?? 'Secundario',
            'type' => 'charge',
            'amount' => $this->chargeAmount,
            'payment_method' => $this->chargePaymentMethod,
            'concept' => $this->chargeConcept,
            'notes' => $this->chargeNotes,
            'created_by' => auth()->id(),
        ]);

        $this->chargeModal = false;
        $this->selected = $this->selected->fresh(['career', 'payments']);
        $this->success('Cobro registrado exitosamente.');

        $this->js("window.open('".route('pre-enrollments.receipt', $payment->id)."', '_blank')");
    }

    public function openRefundModal(int $paymentId): void
    {
        $this->paymentToRefund = PreEnrollmentPayment::with('preEnrollment')->findOrFail($paymentId);
        $this->refundPaymentMethod = $this->paymentToRefund->payment_method;
        $this->refundReason = '';
        $this->drawer = false;
        $this->refundModal = true;
    }

    public function processRefund(): void
    {
        if (! $this->paymentToRefund) {
            return;
        }

        $this->validate([
            'refundReason' => 'nullable|string|max:255',
            'refundPaymentMethod' => 'required|string',
        ]);

        // Generar concepto invertido directamente sin texto fijo redundante
        $conceptInvertido = 'Devolución de: '.$this->paymentToRefund->concept;
        if (! empty(trim($this->refundReason))) {
            $conceptInvertido .= ' ('.trim($this->refundReason).')';
        }

        $refund = PreEnrollmentPayment::create([
            'pre_enrollment_id' => $this->paymentToRefund->pre_enrollment_id,
            'cycle_id' => $this->paymentToRefund->cycle_id,
            'doc_type' => $this->paymentToRefund->doc_type,
            'doc_number' => $this->paymentToRefund->doc_number,
            'payer_name' => $this->paymentToRefund->payer_name,
            'career_name' => $this->paymentToRefund->career_name,
            'type' => 'refund',
            'related_payment_id' => $this->paymentToRefund->id,
            'amount' => $this->paymentToRefund->amount,
            'payment_method' => $this->refundPaymentMethod,
            'concept' => $conceptInvertido,
            'notes' => 'Contra-asiento de Recibo Nº '.$this->paymentToRefund->id,
            'created_by' => auth()->id(),
        ]);

        $this->refundModal = false;
        if ($this->selected) {
            $this->selected = $this->selected->fresh(['career', 'payments']);
        }
        $this->paymentToRefund = null;
        $this->success('Devolución y contra-asiento registrados correctamente.');

        $this->js("window.open('".route('pre-enrollments.receipt', $refund->id)."', '_blank')");
    }

    public function openBoxModal(): void
    {
        if (! $this->boxStartDate) {
            $this->boxStartDate = now()->format('Y-m-d');
        }
        if (! $this->boxEndDate) {
            $this->boxEndDate = now()->format('Y-m-d');
        }
        $this->boxModal = true;
    }

    public function exportBoxCsv(string $separatorType = 'comma')
    {
        $delimiter = match ($separatorType) {
            'semicolon' => ';',
            'tab' => "\t",
            default => ',',
        };

        $query = PreEnrollmentPayment::with(['creator', 'relatedPayment'])->latest();
        if ($this->boxStartDate) {
            $query->whereDate('created_at', '>=', $this->boxStartDate);
        }
        if ($this->boxEndDate) {
            $query->whereDate('created_at', '<=', $this->boxEndDate);
        }
        $records = $query->get();

        $startStr = $this->boxStartDate ? Carbon::parse($this->boxStartDate)->format('Ymd') : 'inicio';
        $endStr = $this->boxEndDate ? Carbon::parse($this->boxEndDate)->format('Ymd') : 'fin';
        $csvFileName = "arqueo_caja_preinscripciones_{$startStr}_{$endStr}.csv";

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=$csvFileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $decimalSeparator = $separatorType === 'semicolon' ? ',' : '.';
        $zeroFormatted = $separatorType === 'semicolon' ? '0,00' : '0.00';

        return response()->stream(function () use ($records, $delimiter, $decimalSeparator, $zeroFormatted) {
            echo "\xEF\xBB\xBF"; // BOM for Excel
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Recibo Nro',
                'Fecha',
                'Hora',
                'Tipo',
                'Aspirante',
                'Tipo Doc',
                'Num Doc',
                'Carrera',
                'Concepto',
                'Metodo de Pago',
                'Debito',
                'Credito',
                'Recibo Origen',
                'Operador',
            ], $delimiter);

            foreach ($records as $row) {
                $isCharge = $row->type === 'charge';
                $formattedAmount = number_format($row->amount, 2, $decimalSeparator, '');

                fputcsv($file, [
                    str_pad($row->id, 6, '0', STR_PAD_LEFT),
                    $row->created_at->format('d/m/Y'),
                    $row->created_at->format('H:i'),
                    $isCharge ? 'Cobro (Ingreso)' : 'Devolución (Egreso)',
                    $row->payer_name,
                    $row->doc_type,
                    $row->doc_number,
                    $row->career_name ?? '-',
                    $row->concept,
                    strtoupper($row->payment_method),
                    $isCharge ? $zeroFormatted : $formattedAmount,
                    $isCharge ? $formattedAmount : $zeroFormatted,
                    $row->related_payment_id ? str_pad($row->related_payment_id, 6, '0', STR_PAD_LEFT) : '-',
                    $row->creator?->name ?? 'Sistema',
                ], $delimiter);
            }
            fclose($file);
        }, 200, $headers);
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
        $this->applyPaymentFilter($query);

        if (! empty(trim($this->search))) {
            $search = trim($this->search);
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('doc_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname'))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname'))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname')), ''), ' ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname')), ''))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname')), ''), ' ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname')), ''))) LIKE ?", [$like]);
            });
        }

        $records = $query->get();

        $csvFileName = 'preinscripciones_'.date('Ymd_His').'.csv';
        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=$csvFileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($records) {
            echo "\xEF\xBB\xBF"; // BOM for Excel
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Apellido y Nombre', 'Tipo Doc', 'Núm Doc', 'Escuela', 'Carrera', 'Email', 'Teléfono', 'Estado',
                'Padre/Madre 1', 'Email P1', 'Tel P1',
                'Padre/Madre 2', 'Email P2', 'Tel P2',
                'Tutor', 'Email Tutor', 'Tel Tutor',
            ]);

            foreach ($records as $row) {
                $name = ($row->payload['lname'] ?? '').', '.($row->payload['fname'] ?? '');
                $escuela = $row->payload['escuela'] ?? '-';

                $p1Name = trim(($row->payload['lname1'] ?? '').', '.($row->payload['fname1'] ?? ''), ', ');
                $p2Name = trim(($row->payload['lname2'] ?? '').', '.($row->payload['fname2'] ?? ''), ', ');
                $tutorName = trim(($row->payload['lname3'] ?? '').', '.($row->payload['fname3'] ?? ''), ', ');

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
                    $row->payload['phone3'] ?? '',
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }

    public function applyPaymentFilter($query)
    {
        if ($this->paymentFilter === 'paid') {
            $query->whereHas('payments', fn ($q) => $q->where('type', 'charge'));
        } elseif ($this->paymentFilter === 'unpaid') {
            $query->whereDoesntHave('payments', fn ($q) => $q->where('type', 'charge'));
        }

        return $query;
    }

    public function render()
    {
        $query = PreEnrollment::with(['career', 'payments'])->latest();
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->cycleFilter) {
            $query->where('cycle_id', $this->cycleFilter);
        }
        $this->applyPaymentFilter($query);

        if (! empty(trim($this->search))) {
            $search = trim($this->search);
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('doc_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname'))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname'))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname')), ''), ' ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname')), ''))) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.fname')), ''), ' ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.lname')), ''))) LIKE ?", [$like]);
            });
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
            'enrolled' => 'Matriculado',
        ];

        $statusCounts = [];
        foreach ($statusMap as $key => $label) {
            $statusCounts[$label] = $rawCounts[$key] ?? 0;
        }

        $totalCount = array_sum($statusCounts);
        $currentLabel = $this->statusFilter ? ($statusMap[$this->statusFilter] ?? 'Todos') : 'Todos';
        $currentCount = $this->statusFilter ? ($statusCounts[$currentLabel] ?? 0) : $totalCount;

        // Balance global de la caja del ciclo
        $cyclePayments = PreEnrollmentPayment::where('cycle_id', $this->cycleFilter ?? AcademicCycle::enrollmentCycle())->get();
        $totalCollected = $cyclePayments->where('type', 'charge')->sum('amount');
        $totalRefunded = $cyclePayments->where('type', 'refund')->sum('amount');
        $boxBalance = $totalCollected - $totalRefunded;

        // Arqueo de caja por rango de fechas (para el modal)
        $auditPaymentsQuery = PreEnrollmentPayment::with(['creator', 'relatedPayment'])->latest();
        if ($this->boxStartDate) {
            $auditPaymentsQuery->whereDate('created_at', '>=', $this->boxStartDate);
        }
        if ($this->boxEndDate) {
            $auditPaymentsQuery->whereDate('created_at', '<=', $this->boxEndDate);
        }
        $auditPayments = $auditPaymentsQuery->get();

        $auditTotalCharges = $auditPayments->where('type', 'charge')->sum('amount');
        $auditTotalRefunds = $auditPayments->where('type', 'refund')->sum('amount');
        $auditBalance = $auditTotalCharges - $auditTotalRefunds;

        return view('livewire.pre-enrollments.index', [
            'preEnrollments' => $query->paginate(100),
            'statusCounts' => $statusCounts,
            'totalCount' => $totalCount,
            'currentLabel' => $currentLabel,
            'currentCount' => $currentCount,
            'boxTotalCollected' => $totalCollected,
            'boxTotalRefunded' => $totalRefunded,
            'boxBalance' => $boxBalance,
            'auditPayments' => $auditPayments,
            'auditTotalCharges' => $auditTotalCharges,
            'auditTotalRefunds' => $auditTotalRefunds,
            'auditBalance' => $auditBalance,
        ]);
    }
}
