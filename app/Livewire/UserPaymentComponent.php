<?php

namespace App\Livewire;

use App\Models\PaymentRecord;
use App\Models\PlansMaster;
use App\Models\User;
use App\Models\UserPayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class UserPaymentComponent extends Component
{
    use Toast;

    public $userId;

    public $user;

    public $openModal = false;

    public $selectedPlan;

    public $payPlans;

    public $combinePlans = false;

    public $paymentModal = false;

    public $paymentDescription;

    public $paymentAmountPaid;

    public $paymentAmountInput;

    public $modifyPaymentModal = false;

    public $paymentId;

    public $totalPaid;

    public $totalDebt;

    public $hasCounter;

    public $userPayments;

    public $plansError = false;

    public $nextPaymentToPay = null;

    public function mount($user = null)
    {
        $this->userId = $user ?? auth()->id();
        $this->user = User::find($this->userId);
        $this->loadData();
        if ($this->payPlans->isNotEmpty()) {
            $this->selectedPlan = $this->payPlans->first()->id;
        }

        if (auth()->user()->hasRole('student')) {
            $this->findNextPayment();
        }
    }

    public function findNextPayment()
    {
        $pending = UserPayments::where('user_id', $this->userId)
            ->whereRaw('paid < amount')
            ->get();

        if ($pending->isNotEmpty()) {
            $this->nextPaymentToPay = $pending->sortBy('date')->first();
        }
    }

    public function loadData()
    {
        $this->userPayments = UserPayments::where('user_id', $this->userId)->get();
        $this->totalDebt = $this->userPayments->sum('amount');
        $this->totalPaid = $this->userPayments->sum('paid');

        try {
            $this->payPlans = PlansMaster::all();
        } catch (\Illuminate\Database\QueryException $e) {
            $this->plansError = true;
            $this->payPlans = collect();
        }

        $this->hasCounter = $this->userPayments->count();

        foreach ($this->userPayments as $userPayment) {
            $textColor = ($userPayment->paid == $userPayment->amount) ? 'text-green-200' : 'text-blue-200';
            $textColor = ($userPayment->paid < $userPayment->amount && $userPayment->paid > 0) ? 'text-amber-200' : $textColor;
            $bgColor = ($userPayment->paid == $userPayment->amount) ? 'bg-green-700' : 'bg-blue-700';
            $bgColor = ($userPayment->paid < $userPayment->amount && $userPayment->paid > 0) ? 'bg-amber-600' : $bgColor;
            $userPayment->textColor = $textColor;
            $userPayment->bgColor = $bgColor;
        }
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
        $this->loadData();
        $this->success('Plan asignado.');
    }

    public function addPaymentToUser()
    {
        $pending = UserPayments::where('user_id', $this->userId)
            ->whereRaw('paid < amount')
            ->get();

        if ($pending->isNotEmpty()) {
            $paymentToPay = $pending->sortBy('date')->first();
            $this->paymentId = $paymentToPay->id;
            $this->paymentDescription = $paymentToPay->title;
            $this->paymentAmountPaid = $paymentToPay->amount - $paymentToPay->paid;
            $this->paymentModal = true;
        } else {
            $this->info('No hay cuotas pendientes de pago.');
        }
    }

    public function registerUserPayment()
    {
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
        $this->loadData();
        $this->success('Pago registrado.');
    }

    public function handleInstallmentClick($userPayment)
    {
        if ($userPayment['paid'] < $userPayment['amount']) {
            // Open payment modal
            $this->paymentId = $userPayment['id'];
            $this->paymentDescription = $userPayment['title'];
            $this->paymentAmountPaid = $userPayment['amount'] - $userPayment['paid'];
            $this->paymentModal = true;
        } else {
            // Open modify modal
            $this->paymentId = $userPayment['id'];
            $this->paymentDescription = $userPayment['title'];
            $this->paymentAmountPaid = $userPayment['amount'];
            $this->totalPaid = $userPayment['paid'];
            $this->modifyPaymentModal = true;
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
        $this->loadData();
        $this->info('Monto modificado.');
    }

    public function deletePayment($paymentId)
    {
        UserPayments::destroy($paymentId);
        $this->modifyPaymentModal = false;
        $this->loadData();
        $this->error('Pago eliminado.');
    }

    public function render()
    {
        return view('livewire.user-payment-component');
    }
}
