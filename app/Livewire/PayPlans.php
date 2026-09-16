<?php

namespace App\Livewire;

use App\Models\PaymentPlan;
use App\Models\PaymentPlanDetail;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class PayPlans extends Component
{
    use WithPagination;

    // Public properties for form inputs and modal state
    public $masterId = 0;

    public $masterTitle = '';

    public $detailId = 0;

    public $detailDate = '';

    public $detailTitle = '';

    public $detailAmount = 0;

    public $payPlan = 1; // Selected master plan ID

    public $openModal = false;

    public $updatePayPlanForm = false;

    public $updatePaymentForm = false;

    public $planDetails = [];

    public $defaultAmount = 0;

    protected $listeners = ['deleteMasterData', 'deleteDetailData'];

    protected $rules = [
        'masterTitle' => 'required|string|max:255',
        'planDetails.*.title' => 'required|string',
        'planDetails.*.amount' => 'required|numeric|min:0',
        'planDetails.*.date' => 'required|date',
    ];

    public function updatedDefaultAmount($value)
    {
        if ($this->masterId == 0) {
            foreach ($this->planDetails as $index => $detail) {
                $this->planDetails[$index]['amount'] = $value;
            }
        }
    }

    // Computed property for all master plans
    #[Computed]
    public function allPaymentPlans()
    {
        return PaymentPlan::all();
    }

    // Computed property for details of the selected plan
    #[Computed]
    public function currentPaymentPlanDetails()
    {
        return PaymentPlanDetail::where('plans_master_id', '=', $this->payPlan)->orderBy('date')->get();
    }

    public function mount()
    {
        // Set initial payPlan if there are any masters
        if ($this->allPaymentPlans->isNotEmpty()) {
            $this->payPlan = $this->allPaymentPlans->first()->id;
        }
    }

    public function render(): mixed
    {
        return view('livewire.pay-plans');
    }

    public function payPlanChanged($id)
    {
        $this->payPlan = $id;
    }

    // --- CRUD Operations for PaymentPlan ---
    public function openCreateMasterForm()
    {
        $this->masterId = 0;
        $this->masterTitle = '';
        $this->planDetails = [];

        // Preload 12 months starting from January of current year
        $year = now()->year;
        for ($i = 1; $i <= 12; $i++) {
            $currentDate = now()->setYear($year)->setMonth($i)->setDay(10);

            $this->planDetails[] = [
                'title' => $this->getMonthName($i).'-'.$year,
                'amount' => 0,
                'date' => $currentDate->format('Y-m-d'),
            ];
        }

        $this->updatePayPlanForm = true;
    }

    private function getMonthName($monthNumber)
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[$monthNumber];
    }

    public function addDetailRow($index)
    {
        $newRow = [
            'title' => '',
            'amount' => 0,
            'date' => now()->format('Y-m-d'),
        ];

        array_splice($this->planDetails, $index + 1, 0, [$newRow]);
    }

    public function removeDetailRow($index)
    {
        unset($this->planDetails[$index]);
        $this->planDetails = array_values($this->planDetails);
    }

    public function createMasterData()
    {
        $this->validate();

        $master = new PaymentPlan;
        $master->title = $this->masterTitle;
        $master->save();

        foreach ($this->planDetails as $detail) {
            PaymentPlanDetail::create([
                'plans_master_id' => $master->id,
                'title' => $detail['title'],
                'amount' => $detail['amount'],
                'date' => $detail['date'],
            ]);
        }

        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPaymentPlans);
        $this->payPlan = $master->id; // Select the newly created master
    }

    public function populateMasterData($id)
    {
        $master = PaymentPlan::find($id);
        $this->masterId = $master->id;
        $this->masterTitle = $master->title;
        $this->updatePayPlanForm = true;
    }

    public function updateMasterData($id)
    {
        $master = PaymentPlan::find($id);
        $master->title = $this->masterTitle;
        $master->save();
        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPaymentPlans);
    }

    public function deleteMasterData($id)
    {
        $master = PaymentPlan::find($id);
        $master->delete();
        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPaymentPlans);
        // Reset payPlan if the deleted master was the selected one
        if ($this->allPaymentPlans->isNotEmpty()) {
            $this->payPlan = $this->allPaymentPlans->first()->id;
        } else {
            $this->payPlan = 1; // Default if no masters left
        }
    }

    // --- CRUD Operations for PaymentPlanDetail ---
    public function openCreateDetailForm()
    {
        $this->detailId = 0;
        $this->detailDate = '';
        $this->detailTitle = '';
        $this->detailAmount = 0;
        $this->updatePaymentForm = true;
    }

    public function createDetailData()
    {
        $detail = new PaymentPlanDetail;
        $detail->date = $this->detailDate;
        $detail->title = $this->detailTitle;
        $detail->amount = $this->detailAmount;
        $detail->plans_master_id = $this->payPlan;
        $detail->save();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPaymentPlanDetails);
    }

    public function populateDetailData($id)
    {
        $detail = PaymentPlanDetail::find($id);
        $this->detailId = $detail->id;
        $this->detailDate = $detail->date->format('Y-m-d');
        $this->detailTitle = $detail->title;
        $this->detailAmount = $detail->amount;
        $this->updatePaymentForm = true;
    }

    public function updateDetailData($id)
    {
        $detail = PaymentPlanDetail::find($id);
        $detail->date = $this->detailDate;
        $detail->title = $this->detailTitle;
        $detail->amount = $this->detailAmount;
        $detail->save();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPaymentPlanDetails);
    }

    public function deleteDetailData($id)
    {
        $detail = PaymentPlanDetail::find($id);
        $detail->delete();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPaymentPlanDetails);
    }
}
