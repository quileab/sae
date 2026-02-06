<?php

namespace App\Livewire;

use App\Models\PaymentRecord;
use App\Models\PlansMaster;
use App\Models\User;
use App\Models\UserPayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class UserPaymentComponent extends Component
{
    use Toast;

    public $userId;

    public $user;

    public $openModal = false;

    public $selectedPlan;

    public $combinePlans = false;

    public $paymentModal = false;

    public $paymentDescription;

    public $paymentAmountPaid;

    public $paymentAmountInput;

    public $modifyPaymentModal = false;

    public $paymentId;

    public $totalDebt;

    public $plansError = false;

    public function mount($user = null)
    {
        $this->userId = $user ?? auth()->id();
        $this->user = User::findOrFail($this->userId);

        if ($this->payPlans->isNotEmpty()) {
            $this->selectedPlan = $this->payPlans->first()->id;
        }
    }

    #[Computed]
    public function nextPaymentToPay()
    {
        return UserPayments::where('user_id', $this->userId)
            ->whereRaw('paid < amount')
            ->orderBy('date')
            ->first();
    }

    #[Computed]
    public function userPayments()
    {
        return UserPayments::where('user_id', $this->userId)
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function payPlans()
    {
        try {
            return PlansMaster::all();
        } catch (\Illuminate\Database\QueryException $e) {
            $this->plansError = true;

            return collect();
        }
    }

    #[Computed]
    public function totals()
    {
        return [
            'debt' => $this->userPayments->sum('amount'),
            'paid' => $this->userPayments->sum('paid'),
        ];
    }

    public function assignPayPlan()
    {
        if (empty($this->selectedPlan)) {
            $this->error('Debe seleccionar un plan.');

            return;
        }

        $planMaster = PlansMaster::with('details')->find($this->selectedPlan);

        if (! $planMaster) {
            $this->error('El plan seleccionado no es válido.');

            return;
        }

        foreach ($planMaster->details as $detail) {
            UserPayments::create([
                'user_id' => $this->userId,
                'amount' => $detail->amount,
                'paid' => 0,
                'date' => $detail->date,
                'title' => $detail->title,
            ]);
        }

        $this->openModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->success('Plan asignado.');
    }

    public function addPaymentToUser()
    {
        $next = $this->nextPaymentToPay;

        if ($next) {
            $this->paymentId = $next->id;
            $this->paymentDescription = $next->title;
            $this->paymentAmountPaid = $next->amount - $next->paid;
            $this->paymentModal = true;
        } else {
            $this->info('No hay cuotas pendientes de pago.');
        }
    }

    public function registerUserPayment()
    {
        if (empty($this->paymentAmountInput) || $this->paymentAmountInput <= 0) {
            $this->error('Debe ingresar un importe válido mayor a 0.');

            return;
        }

        $paymentRecord = null;

        DB::transaction(function () use (&$paymentRecord) {
            $amountToDistribute = $this->paymentAmountInput;
            $currentInstallment = UserPayments::find($this->paymentId);

            if (! $currentInstallment) {
                return;
            }

            // Get all pending installments for the user, starting from the current one
            $installments = UserPayments::where('user_id', $this->userId)
                ->where('date', '>=', $currentInstallment->date)
                ->whereRaw('paid < amount')
                ->orderBy('date', 'asc')
                ->get();

            Carbon::setLocale(config('app.locale'));
            $description = '';

            foreach ($installments as $installment) {
                if ($amountToDistribute <= 0) {
                    break;
                }

                $remainingOnInstallment = $installment->amount - $installment->paid;
                $paymentForThisInstallment = min($amountToDistribute, $remainingOnInstallment);

                $installment->paid += $paymentForThisInstallment;
                $installment->save();

                $amountToDistribute -= $paymentForThisInstallment;

                $descriptionPrefix = '';
                if ($paymentForThisInstallment < $remainingOnInstallment) {
                    $descriptionPrefix = 'par. ';
                }

                // Append the formatted date to the description
                $description .= $descriptionPrefix.ucfirst(Carbon::parse($installment->date)->translatedFormat('M\'y')).' - ';
            }

            // Remove the trailing ' - ' from the description
            $description = rtrim($description, ' - ');

            // Create a single payment record for the total amount paid
            $paymentRecord = PaymentRecord::create([
                'userpayments_id' => $this->paymentId, // ID of the installment that triggered the payment
                'user_id' => $this->userId,
                'paymentBox' => Auth::user()->name,
                'description' => $description,
                'paymentAmount' => $this->paymentAmountInput,
            ]);
        });

        if ($paymentRecord) {
            $this->dispatch('open-receipt', url: route('payments.receipt', $paymentRecord));
        }

        $this->paymentModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->success('Pago registrado.');
    }

    public function handleInstallmentClick($userPaymentId)
    {
        $userPayment = UserPayments::findOrFail($userPaymentId);

        if ($userPayment->paid < $userPayment->amount) {
            // Open payment modal
            $this->paymentId = $userPayment->id;
            $this->paymentDescription = $userPayment->title;
            $this->paymentAmountPaid = $userPayment->amount - $userPayment->paid;
            $this->paymentModal = true;
        } else {
            // Open modify modal
            $this->paymentId = $userPayment->id;
            $this->paymentDescription = $userPayment->title;
            $this->paymentAmountPaid = $userPayment->amount;
            $this->modifyPaymentModal = true;
            $this->totalDebt = $userPayment->amount; // Set default for modification
        }
    }

    public function modifyAmount($paymentId)
    {
        $payment = UserPayments::find($paymentId);
        if ($payment) {
            $payment->amount = $this->totalDebt;
            $payment->save();
        }
        $this->modifyPaymentModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->info('Monto modificado.');
    }

    public function deletePayment($paymentId)
    {
        UserPayments::destroy($paymentId);
        $this->modifyPaymentModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->error('Pago eliminado.');
    }

    public function render()
    {
        return view('livewire.user-payment-component');
    }
}
