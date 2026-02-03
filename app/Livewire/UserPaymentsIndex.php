<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class UserPaymentsIndex extends Component
{
    use WithPagination;

    public $search = '';

    public $perPage = 10;

    #[Computed]
    public function users()
    {
        return User::where('role', 'student')
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('lastname', 'like', '%'.$this->search.'%')
                    ->orWhere('firstname', 'like', '%'.$this->search.'%');
            })
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.user-payments-index');
    }
}
