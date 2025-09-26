<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User; // Assuming User model exists in sae project
use App\Models\PaymentRecord; // Assuming PaymentRecord model exists in sae project
use App\Models\UserPayments; // Assuming UserPayments model exists in sae project

class PaymentsDetails extends Component
{
    use WithPagination;

    // record payment
    public $userId; // Changed from uid
    public $user;

    // auxiliary variables
    public $updating = false;
    public $sort = 'id';
    public $perPage = 10; // Changed from cant
    public $direction = 'desc';
    public $loadData = false;
    public $openModal = false;

    public function mount($id) {
        $this->user = User::find($id);
        $this->userId = $id; // Added userId for consistency
    }

    public function render() {
        $payments = $this->user->payments()
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage); // Changed from cant

        return view('livewire.payments-details',
            compact('payments'));
    }

    public function loadData() {
        $this->loadData = true;
    }

    public function cancelPayment($id) {
        //set Paymentrecord description=CAN and paymentAmount=0
        $payment = PaymentRecord::find($id);
        $amount = $payment->paymentAmount;
        //set UserPayment paid=paid-paymentAmount
        $userPayment = UserPayments::find($payment->userpayments_id);
        $amountPaid = $userPayment->paid;

        do {
            if ($amountPaid < $amount) {
                $amount = $amount - $userPayment->paid;
                $userPayment->paid = 0;
            } else {
                $userPayment->paid = $userPayment->paid - $amount;
                $amount = 0;
            }
            $userPayment->save();
            if ($amount > 0) {
                // search for previous payment
                $userPayment =
                UserPayments::where('user_id', $this->user->id)
                    ->where('id', '<', $userPayment->id)
                    ->orderBy('id', 'desc')
                    ->first();
                $amountPaid = $userPayment->paid;
            }
        } while ($amount > 0);

        //set PaymentRecord description=CAN and paymentAmount=0
        $payment->description = 'CANCELED'; // Translated
        $payment->paymentAmount = 0;
        $payment->save();
    }
}
