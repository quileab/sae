<?php

namespace App\Livewire;

use Livewire\Component;

class ReportPayments extends Component
{
    public $dateFrom;

    public $dateTo;

    public $search = '';

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        return view('livewire.report-payments');
    }
}
