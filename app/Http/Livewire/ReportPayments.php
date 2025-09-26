<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ReportPayments extends Component
{

    public $dateFrom, $dateTo;
    public $search = '';

    public function mount()
    {
        $this->dateFrom = date('Y-m-d');
        $this->dateTo = date('Y-m-d');
    }

    public function render()
    {
        return view('livewire.report-payments');
    }
}
