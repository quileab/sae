<?php

namespace App\Livewire;

use Livewire\Component;

class SessionBookmarks extends Component
{
    public $app_session = [];

    public function mount(): void
    {
        // app_session array icon, value, label
        // 'user_id', 'career_id', 'subject_id', 'cycle'
        $this->app_session = [
            ['icon' => 'o-user', 'value' => session('user_id'), 'label' => session('user_id_name')],
            ['icon' => 'o-academic-cap', 'value' => session('career_id'), 'label' => session('career_id_name')],
            ['icon' => 'o-queue-list', 'value' => session('subject_id'), 'label' => session('subject_id_name')],
            ['icon' => 'o-calendar', 'value' => session('cycle_id'), 'label' => session('cycle_id_name')],
        ];

    }

    public function render()
    {
        return view('livewire.session_bookmarks');
    }
}
