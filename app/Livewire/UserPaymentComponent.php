<?php

namespace App\Livewire;

use App\Models\PaymentPlan;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class UserPaymentComponent extends Component
{
    use Toast;

    #[Locked]
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

    #[Locked]
    public $paymentId;

    #[Locked]
    public $totalDebt;

    public $plansError = false;

    /**
     * Ensure a student can only operate on their own payments; staff may operate on any user.
     */
    protected function assertOwnsTargetUser(): void
    {
        $auth = Auth::user();

        if ($auth->hasRole('student') && (int) $this->userId !== $auth->id) {
            abort(403, 'No tienes permiso para gestionar los pagos de otro usuario.');
        }
    }

    /**
     * Ensure the given UserPayment belongs to the authenticated student (when applicable).
     */
    protected function assertOwnsPayment(UserPayment $payment): void
    {
        $auth = Auth::user();

        if ($auth->hasRole('student') && $payment->user_id !== $auth->id) {
            abort(403, 'No tienes permiso para gestionar este pago.');
        }
    }

    /**
     * Staff-only financial operations (adjusting owed amounts).
     */
    protected function assertStaffOnly(): void
    {
        abort_if(! Auth::user()->isStaff(), 403, 'Se requieren permisos de personal para realizar esta acción.');
    }

    public function mount($user = null)
    {
        if ($user) {
            $this->userId = $user;
            $this->user = User::find($this->userId);
        } elseif (auth()->user()->hasRole('student')) {
            $this->userId = auth()->id();
            $this->user = auth()->user();
        }

        if ($this->user && $this->payPlans->isNotEmpty()) {
            $this->selectedPlan = $this->payPlans->first()->id;
        }
    }

    #[Computed]
    public function nextPaymentToPay()
    {
        if (! $this->userId) {
            return null;
        }

        return UserPayment::where('user_id', $this->userId)
            ->whereRaw('paid < amount')
            ->orderBy('date')
            ->first();
    }

    #[Computed]
    public function userPayments()
    {
        if (! $this->userId) {
            return collect();
        }

        return UserPayment::where('user_id', $this->userId)
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function payPlans()
    {
        try {
            return PaymentPlan::orderBy('title')->get(['id', 'title']);
        } catch (QueryException $e) {
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
        $this->assertOwnsTargetUser();

        if (empty($this->selectedPlan)) {
            $this->error('Debe seleccionar un plan.');

            return;
        }

        $planMaster = PaymentPlan::with('details')->find($this->selectedPlan);

        if (! $planMaster) {
            $this->error('El plan seleccionado no es válido.');

            return;
        }

        DB::transaction(function () use ($planMaster) {
            foreach ($planMaster->details as $detail) {
                $installment = null;

                if ($this->combinePlans) {
                    $installment = UserPayment::where('user_id', $this->userId)
                        ->whereDate('date', $detail->date)
                        ->first();
                }

                if ($installment) {
                    $updateData = ['title' => $detail->title];

                    // Protect fully paid Installment from amount changes
                    if ($installment->paid < $installment->amount) {
                        $updateData['amount'] = $detail->amount;
                    }

                    $installment->update($updateData);
                } else {
                    UserPayment::create([
                        'user_id' => $this->userId,
                        'amount' => $detail->amount,
                        'paid' => 0,
                        'date' => $detail->date,
                        'title' => $detail->title,
                    ]);
                }
            }
        });

        $this->openModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->success('Plan asignado.');
    }

    public function addPaymentToUser()
    {
        $next = $this->nextPaymentToPay;

        if ($next) {
            $this->setPaymentDefaults($next);
            $this->paymentModal = true;
        } else {
            $this->info('No hay cuotas pendientes de pago.');
        }
    }

    private function setPaymentDefaults($userPayment)
    {
        $this->paymentId = $userPayment->id;
        $this->paymentDescription = $userPayment->title;
        $this->paymentAmountPaid = $userPayment->amount - $userPayment->paid;

        // Balance until today, starting from the selected installment
        $balance = UserPayment::where('user_id', $this->userId)
            ->where('date', '>=', $userPayment->date)
            ->where('date', '<=', now())
            ->selectRaw('COALESCE(SUM(GREATEST(0, amount - paid)), 0) as balance')
            ->value('balance') ?? 0;

        $this->paymentAmountInput = $balance > 0 ? $balance : $this->paymentAmountPaid;
    }

    public function registerUserPayment()
    {
        $this->assertOwnsTargetUser();

        if (empty($this->paymentAmountInput) || $this->paymentAmountInput <= 0) {
            $this->error('Debe ingresar un importe válido mayor a 0.');

            return;
        }

        $paymentRecord = null;

        DB::transaction(function () use (&$paymentRecord) {
            $amountToDistribute = $this->paymentAmountInput;
            $currentInstallment = UserPayment::find($this->paymentId);

            if (! $currentInstallment) {
                return;
            }

            $this->assertOwnsPayment($currentInstallment);
            $this->userId = $currentInstallment->user_id;

            // Get all pending Installment for the user, starting from the current one
            $Installment = UserPayment::where('user_id', $this->userId)
                ->where('date', '>=', $currentInstallment->date)
                ->whereRaw('paid < amount')
                ->orderBy('date', 'asc')
                ->get();

            Carbon::setLocale(config('app.locale'));
            $description = '';

            foreach ($Installment as $installment) {
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

                // Append the title to the description
                $description .= $descriptionPrefix.$installment->title.' - ';
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
        $userPayment = UserPayment::findOrFail($userPaymentId);
        $this->assertOwnsPayment($userPayment);

        if ($userPayment->paid < $userPayment->amount) {
            // Open payment modal
            $this->setPaymentDefaults($userPayment);
            $this->paymentModal = true;
        } else {
            // Open modify modal
            $this->openModifyModal($userPaymentId);
        }
    }

    public function openModifyModal($userPaymentId)
    {
        $this->assertStaffOnly();

        $userPayment = UserPayment::findOrFail($userPaymentId);
        $this->paymentId = $userPayment->id;
        $this->paymentDescription = $userPayment->title;
        $this->paymentAmountPaid = $userPayment->amount;
        $this->modifyPaymentModal = true;
        $this->totalDebt = $userPayment->amount; // Set default for modification
    }

    public function modifyAmount($paymentId)
    {
        $this->assertStaffOnly();

        $payment = UserPayment::find($paymentId);
        if ($payment) {
            $payment->amount = $this->totalDebt;
            $payment->save();
        }
        $this->modifyPaymentModal = false;
        unset($this->userPayments);
        unset($this->totals);
        $this->info('Monto modificado.');
    }

    public function render()
    {
        return view('livewire.user-payment-component');
    }
}
