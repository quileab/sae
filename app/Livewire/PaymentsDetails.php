<?php

namespace App\Livewire;

use App\Models\PaymentInvoice;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
use App\Services\AfipService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class PaymentsDetails extends Component
{
    use Toast, WithPagination;

    public $openModal = false;

    public $updating = false;

    public $perPage = 10;

    public $user;

    public function mount($user)
    {
        if (auth()->user()->hasRole('student') && auth()->id() != $user) {
            abort(403, 'No tienes permiso para ver esta página.');
        }

        $this->user = User::find($user);
    }

    public function save()
    {
        // Logic to save or update a payment record
        // This seems to be a placeholder, as there is no form in the modal
        $this->openModal = false;
        $this->success('Registro guardado.');
    }

    public function cancelPayment($paymentId)
    {
        // IDOR Prevention: Only authorized staff can cancel payments
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'treasurer'])) {
            abort(403, 'No tienes permiso para cancelar pagos.');
        }

        DB::transaction(function () use ($paymentId) {
            $payment = PaymentRecord::find($paymentId);

            if ($payment && $payment->description != 'CANCELADO') {
                $amountToRevert = $payment->paymentAmount;

                $UserPayment = UserPayment::where('user_id', $payment->UserPayment->user_id)
                    ->where('paid', '>', 0)
                    ->orderBy('date', 'desc')
                    ->get();

                foreach ($UserPayment as $userpayment) {
                    if ($amountToRevert <= 0) {
                        break;
                    }

                    $paidAmount = $userpayment->paid;
                    $revertAmount = min($amountToRevert, $paidAmount);

                    $userpayment->paid -= $revertAmount;
                    $userpayment->save();

                    $amountToRevert -= $revertAmount;
                }

                $payment->description = 'CANCELADO';
                $payment->paymentAmount = 0;
                $payment->save();

                $this->success('Pago cancelado.');
            } else {
                $this->error('Pago no encontrado o ya cancelado.');
            }
        });
    }

    #[Computed]
    public function payments()
    {
        return PaymentRecord::whereHas('UserPayment', function ($query) {
            $query->where('user_id', $this->user->id);
        })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }

    public array $selected = [];

    public function generateAfipInvoice(AfipService $afipService)
    {
        if (empty($this->selected)) {
            $this->warning('Debe seleccionar al menos un pago.');

            return;
        }

        $payments = PaymentRecord::whereIn('id', $this->selected)->get();

        $alreadyInvoiced = $payments->whereNotNull('payment_invoice_id');
        if ($alreadyInvoiced->count() > 0) {
            $this->error('Algunos de los pagos seleccionados ya fueron facturados.');

            return;
        }

        $totalAmount = $payments->sum('paymentAmount');
        if ($totalAmount <= 0) {
            $this->error('El monto total debe ser mayor a 0.');

            return;
        }

        // ARCA (ex AFIP) 2026 rule: Identificación obligatoria si supera $10.000.000 (RG 5866/2026)
        if ($totalAmount >= 10000000) {
            $this->error('Los comprobantes superiores a $10.000.000 requieren DNI/CUIT (RG 5866/2026). El sistema no registra DNI actualmente.');

            return;
        }

        try {
            DB::transaction(function () use ($payments, $totalAmount, $afipService) {
                // Here we would construct the AFIP data array.
                // Using dummy data for voucher logic as feav2 example
                $data = [
                    'CantReg' => 1,
                    'PtoVta' => 1, // Get from config
                    'CbteTipo' => 11, // Factura C (example)
                    'Concepto' => 2, // Servicios
                    'DocTipo' => 99, // Consumidor final
                    'DocNro' => 0,
                    'CbteDesde' => 1,
                    'CbteHasta' => 1,
                    'CbteFch' => intval(date('Ymd')),
                    'ImpTotal' => $totalAmount,
                    'ImpTotConc' => 0,
                    'ImpNeto' => $totalAmount,
                    'ImpOpEx' => 0,
                    'ImpIVA' => 0,
                    'ImpTrib' => 0,
                    'MonId' => 'PES',
                    'MonCotiz' => 1,
                ];

                // Get last voucher number
                $lastVoucher = $afipService->getLastVoucher($data['PtoVta'], $data['CbteTipo']);
                $data['CbteDesde'] = $lastVoucher + 1;
                $data['CbteHasta'] = $lastVoucher + 1;

                $res = $afipService->createVoucher($data);

                if (isset($res['CAE'])) {
                    $invoice = PaymentInvoice::create([
                        'voucher_type' => $data['CbteTipo'],
                        'point_of_sales' => $data['PtoVta'],
                        'voucher_number' => $data['CbteDesde'],
                        'cae' => $res['CAE'],
                        'cae_expiration' => $res['CAEFchVto'],
                        'afip_response' => $res,
                    ]);

                    foreach ($payments as $payment) {
                        $payment->payment_invoice_id = $invoice->id;
                        $payment->save();
                    }

                    $this->success("Factura AFIP Creada. CAE: {$res['CAE']}");
                    $this->selected = [];
                } else {
                    throw new \Exception('AFIP no devolvió CAE.');
                }
            });
        } catch (\Exception $e) {
            $this->error('Error AFIP: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.payments-details');
    }
}
