<div>
    <div class="flex justify-between items-center mb-4">
        <x-button icon="o-arrow-left" wire:click="previousMonth" class="btn-circle btn-primary" />
        <h2 class="text-2xl font-bold">{{ $date->translatedFormat('F Y') }}</h2>
        <x-button icon="o-arrow-right" wire:click="nextMonth" class="btn-circle btn-primary" />
    </div>

    <div class="flex justify-between items-center mb-4">
        @if (!auth()->user()->hasRole('student'))
            <x-button label="Crear Evento" icon="o-calendar" wire:click="dateClick('{{ now()->format('Y-m-d') }}')"
                class="btn-primary" />
        @endif
        <x-select wire:model.live="career_id" :options="$careers" option-value="id" option-label="name"
            placeholder="Todas las carreras" :disabled="auth()->user()->hasRole('student')" />
    </div>

    <div class="grid grid-cols-7 gap-1">
        @foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $day)
            <div class="text-center font-bold">{{ $day }}</div>
        @endforeach

        @foreach ($calendarGrid as $week)
            @foreach ($week as $day)
                <div
                    class="text-center rounded-md overflow-hidden p-0 {{ $day['isCurrentMonth'] ? 'bg-gray-100/10' : 'bg-black/10 text-gray-600' }}
                                                                    {{ Carbon\Carbon::parse($day['date'])->isToday() ? 'ring-2 ring-primary bg-primary/30 ' : '' }}">
                    {{ $day['day'] }}
                    <div class="mt-2">
                        @foreach ($day['events'] as $event)
                            <div class="rounded p-1 cursor-pointer"
                                style="background-color: {{ $event->color }}60; border: 2px solid {{ $event->color }};"
                                wire:click="eventClick({{ $event->id }})" title="{{ $event->description }}">
                                <small>{{ $event->title }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <div class="mt-4">
        @foreach ($calendarGrid as $week)
            @foreach ($week as $day)
                @if ($day['events']->count() > 0)
                    <div class="mb-4">
                        <h4 class="text-warning p-1 mb-1 rounded-md text-lg bg-gray-900">
                            {{ Carbon\Carbon::parse($day['date'])->translatedFormat('d/m/Y') }}
                        </h4>

                        @foreach ($day['events'] as $event)
                            <p>
                            <div class="ml-4 w-3 h-3 rounded-full inline-block" style="background-color: {{ $event->color }};"></div>
                            <span class="font-bold">
                                {{ $event->title }}</span>:
                            de {{ Carbon\Carbon::parse($event->start)->format('H:i') }}hs
                            a {{ Carbon\Carbon::parse($event->end)->format('H:i') }}hs
                            @if ($event->subject)
                                <span class="text-gray-400">| {{ $event->subject->name }} ({{ $event->subject->id }})</span>
                            @endif
                            </p>
                            @if ($event->description)
                                <p class="ml-8 text-gray-400">{{ $event->description }}</p>
                            @endif
                            @if ($event->presidente)
                                <p class="ml-8 text-gray-500">Presidente: {{ $event->presidente->full_name }}</p>
                            @endif
                            @if ($event->vocal1)
                                <p class="ml-8 text-gray-500">Vocal 1: {{ $event->vocal1->full_name }}</p>
                            @endif
                            @if ($event->vocal2)
                                <p class="ml-8 text-gray-500">Vocal 2: {{ $event->vocal2->full_name }}</p>
                            @endif
                        @endforeach

                    </div>
                @endif
            @endforeach
        @endforeach
    </div>

    <livewire:event-form />
</div>