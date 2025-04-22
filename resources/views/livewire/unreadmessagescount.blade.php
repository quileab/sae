<?php

use Livewire\Volt\Component;

new class extends Component {

    public function count()
    {
        return \App\Models\MessageRead::where('user_id', Auth::id())
            ->where('read_at', null)
            ->count();
    }

}; ?>

<div>
    @if($count() > 0)
        <x-icon name="o-bell-alert" class="text-primary" label="{{ $count() }}" />
    @else
        <x-icon name="o-bell" class="text-primary" />
    @endif
</div>
