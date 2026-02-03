<?php

namespace App\Livewire;

use App\Models\PlansDetail;
use App\Models\PlansMaster;
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

    protected $listeners = ['deleteMasterData', 'deleteDetailData'];

    // Computed property for all master plans
    #[Computed]
    public function allPlansMasters()
    {
        return PlansMaster::all();
    }

    // Computed property for details of the selected plan
    #[Computed]
    public function currentPlansDetails()
    {
        return PlansDetail::where('plans_master_id', '=', $this->payPlan)->orderBy('date')->get();
    }

    public function mount()
    {
        // Set initial payPlan if there are any masters
        if ($this->allPlansMasters->isNotEmpty()) {
            $this->payPlan = $this->allPlansMasters->first()->id;
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

    // --- CRUD Operations for PlansMaster ---
    public function openCreateMasterForm()
    {
        $this->masterId = 0;
        $this->masterTitle = '';
        $this->updatePayPlanForm = true;
    }

    public function createMasterData()
    {
        $master = new PlansMaster;
        $master->title = $this->masterTitle;
        $master->save();
        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPlansMasters);
        $this->payPlan = $master->id; // Select the newly created master
    }

    public function populateMasterData($id)
    {
        $master = PlansMaster::find($id);
        $this->masterId = $master->id;
        $this->masterTitle = $master->title;
        $this->updatePayPlanForm = true;
    }

    public function updateMasterData($id)
    {
        $master = PlansMaster::find($id);
        $master->title = $this->masterTitle;
        $master->save();
        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPlansMasters);
    }

    public function deleteMasterData($id)
    {
        $master = PlansMaster::find($id);
        $master->delete();
        $this->updatePayPlanForm = false;
        // Invalidate computed property to refresh data
        unset($this->allPlansMasters);
        // Reset payPlan if the deleted master was the selected one
        if ($this->allPlansMasters->isNotEmpty()) {
            $this->payPlan = $this->allPlansMasters->first()->id;
        } else {
            $this->payPlan = 1; // Default if no masters left
        }
    }

    // --- CRUD Operations for PlansDetail ---
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
        $detail = new PlansDetail;
        $detail->date = $this->detailDate;
        $detail->title = $this->detailTitle;
        $detail->amount = $this->detailAmount;
        $detail->plans_master_id = $this->payPlan;
        $detail->save();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPlansDetails);
    }

    public function populateDetailData($id)
    {
        $detail = PlansDetail::find($id);
        $this->detailId = $detail->id;
        $this->detailDate = $detail->date;
        $this->detailTitle = $detail->title;
        $this->detailAmount = $detail->amount;
        $this->updatePaymentForm = true;
    }

    public function updateDetailData($id)
    {
        $detail = PlansDetail::find($id);
        $detail->date = $this->detailDate;
        $detail->title = $this->detailTitle;
        $detail->amount = $this->detailAmount;
        $detail->save();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPlansDetails);
    }

    public function deleteDetailData($id)
    {
        $detail = PlansDetail::find($id);
        $detail->delete();
        $this->updatePaymentForm = false;
        // Invalidate computed property to refresh data
        unset($this->currentPlansDetails);
    }
}
