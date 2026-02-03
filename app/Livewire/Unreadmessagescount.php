<?php

namespace App\Livewire;

use App\Models\MessageRead;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Unreadmessagescount extends Component
{
    public function count()
    {
        return MessageRead::where('user_id', Auth::id())
            ->where('read_at', null)
            ->count();
    }

    public function render()
    {
        return view('livewire.unreadmessagescount', [
            'count' => $this->count(),
        ]);
    }
}
