<?php

use Livewire\Volt\Component;
use App\Models\MessageRead;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $type = 'desktop';

    public function with(): array
    {
        $count = 0;
        if (Auth::check()) {
            $count = MessageRead::where('user_id', Auth::id())
                ->whereNull('read_at')
                ->count();
        }
        return [
            'count' => $count
        ];
    }
}; ?>

@if($type === 'mobile')
    <div class="inline-flex items-center" x-on:message-read.window="$wire.$refresh()">
        @if($count > 0)
            <x-button icon="o-chat-bubble-left-right" link="/chat" no-wire-navigate class="btn-ghost btn-sm text-primary relative me-2">
                <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-error text-[9px] font-bold text-white">
                    {{ $count }}
                </span>
            </x-button>
        @else
            <x-button icon="o-chat-bubble-left-right" link="/chat" no-wire-navigate class="btn-ghost btn-sm text-base-content/70 me-2" />
        @endif
    </div>
@else
    {{-- Desktop / Sidebar --}}
    @if($count > 0)
        <x-menu-item title="Comunicación" icon="o-chat-bubble-left-right" link="/chat" no-wire-navigate badge="{{ $count }}" badge-classes="badge-error text-white font-bold" />
    @else
        <x-menu-item title="Comunicación" icon="o-chat-bubble-left-right" link="/chat" no-wire-navigate />
    @endif
@endif
