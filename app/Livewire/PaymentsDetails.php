<?php

namespace App\Livewire;

use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
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
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.payments-details');
    }
}
