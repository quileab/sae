<?php

use Livewire\Volt\Component;

new class extends Component {
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
    //
}; ?>

<div class="grid gap-0 border-b border-white/50 w-full align-middle">
    <div class="grid gap-0 grid-cols-4">
        @foreach($app_session as $item)
            <x-icon name="{{ $item['icon'] }}" label="{{ $item['label'] }}" class="text-primary" />
        @endforeach
    </div>
</div>