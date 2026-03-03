<div>
    <!-- Header -->
    <x-header title="{{ ucfirst($date->translatedFormat('F Y')) }}" separator progress-indicator>
        <x-slot:actions>
            <x-button icon="o-chevron-left" wire:click="previousMonth" class="btn-circle btn-ghost" spinner="previousMonth" />
            <x-button icon="o-chevron-right" wire:click="nextMonth" class="btn-circle btn-ghost" spinner="nextMonth" />
            @if (!auth()->user()->hasRole('student'))
                <x-button label="Crear Evento" icon="o-calendar" wire:click="dateClick('{{ now()->format('Y-m-d') }}')" class="btn-primary" responsive />
            @endif
        </x-slot:actions>
    </x-header>

    <!-- Filters -->
    <div class="mb-6 flex justify-end">
        <x-select wire:model.live="career_id" :options="$careers" option-value="id" option-label="name"
            placeholder="Todas las carreras" :disabled="auth()->user()->hasRole('student')" class="max-w-xs" icon="o-academic-cap" />
    </div>

    <!-- Calendar Grid -->
    <x-card class="p-0 overflow-hidden" shadow>
        <div class="grid grid-cols-7 gap-px lg:gap-1 bg-base-300 dark:bg-base-100/50 rounded-t-xl overflow-hidden">
            @foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $day)
                <div class="text-center font-bold text-xs lg:text-sm py-2 bg-base-200/50 uppercase">{{ $day }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-px bg-base-300 dark:bg-base-100/50">
            @foreach ($this->calendarGrid as $week)
                @foreach ($week as $day)
                    @php
                        $isToday = \Carbon\Carbon::parse($day['date'])->isToday();
                        $bgClass = $day['isCurrentMonth'] ? 'bg-base-100 dark:bg-base-200/50' : 'bg-base-200/30 dark:bg-base-300/30 text-base-content/40';
                        if ($isToday) $bgClass = 'bg-primary/5 ring-inset ring-2 ring-primary';
                    @endphp
                    <div class="min-h-[80px] lg:min-h-[120px] p-1 lg:p-2 relative transition-colors duration-200 hover:bg-base-200/80 cursor-pointer {{ $bgClass }}" 
                         wire:click="dateClick('{{ $day['date'] }}')">
                        <div class="flex justify-between items-start">
                            <span class="text-xs lg:text-sm font-semibold {{ $isToday ? 'text-primary' : '' }}">
                                {{ $day['day'] }}
                            </span>
                            @if(count($day['events']) > 0)
                                <span class="lg:hidden badge badge-xs badge-primary badge-outline">{{ count($day['events']) }}</span>
                            @endif
                        </div>
                        
                        <div class="mt-1 flex-col gap-1 overflow-y-auto max-h-24 no-scrollbar hidden lg:flex">
                            @foreach ($day['events'] as $event)
                                <div class="text-[10px] leading-none px-1.5 py-1 rounded cursor-pointer hover:opacity-80 transition-opacity truncate shadow-sm font-medium text-white"
                                    style="background-color: {{ $event->color }};"
                                    wire:click.stop="eventClick({{ $event->id }})" title="{{ $event->description }}">
                                    {{ \Carbon\Carbon::parse($event->start)->format('H:i') }} - {{ $event->title }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </x-card>

    <!-- Upcoming Events List (mobile and desktop) -->
    <div class="mt-8">
        <x-header title="Eventos del Mes" size="text-xl" separator />
        
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->calendarGrid as $week)
                @foreach ($week as $day)
                    @if (count($day['events']) > 0 && $day['isCurrentMonth'])
                        <x-card class="shadow-sm items-start" title="{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('d F, Y') }}">
                            <x-slot:title>
                                <div class="flex items-center gap-2 text-md font-bold text-primary">
                                    <x-icon name="o-calendar-days" class="w-5 h-5" />
                                    {{ \Carbon\Carbon::parse($day['date'])->translatedFormat('d M, Y') }}
                                </div>
                            </x-slot:title>

                            <div class="flex flex-col gap-4">
                                @foreach ($day['events'] as $event)
                                    <div class="border-l-4 pl-3 py-1 bg-base-200/50 rounded-r-md transition-colors hover:bg-base-300/50" style="border-color: {{ $event->color }};">
                                        <div class="font-bold flex flex-col md:flex-row md:justify-between md:items-center gap-1">
                                            <span class="text-sm cursor-pointer hover:underline" wire:click="eventClick({{ $event->id }})">
                                                {{ $event->title }}
                                            </span>
                                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-base-300 self-start md:self-auto">
                                                {{ \Carbon\Carbon::parse($event->start)->format('H:i') }} a {{ \Carbon\Carbon::parse($event->end)->format('H:i') }}h
                                            </span>
                                        </div>

                                        @if ($event->subject)
                                            <div class="text-xs font-semibold mt-2 flex items-center gap-1 truncate text-base-content/80" title="{{ $event->subject->name }}">
                                                <x-icon name="o-book-open" class="w-4 h-4" />
                                                {{ $event->subject->name }}
                                            </div>
                                        @endif
                                        
                                        @if ($event->description)
                                            <p class="text-xs text-base-content/60 mt-1 italic">{{ $event->description }}</p>
                                        @endif
                                        
                                        @if ($event->presidente || $event->vocal1 || $event->vocal2)
                                            <div class="mt-3 text-xs space-y-1 p-2 bg-base-100 rounded border border-base-300">
                                                @if($event->presidente) <div class="text-base-content/80"><span class="font-semibold text-primary/80">Presidente:</span> {{ $event->presidente->full_name }}</div> @endif
                                                @if($event->vocal1) <div class="text-base-content/80"><span class="font-semibold text-secondary/80">Vocal 1:</span> {{ $event->vocal1->full_name }}</div> @endif
                                                @if($event->vocal2) <div class="text-base-content/80"><span class="font-semibold text-secondary/80">Vocal 2:</span> {{ $event->vocal2->full_name }}</div> @endif
                                            </div>
                                        @endif

                                        @if(auth()->user()->hasAnyRole(['admin', 'director', 'administrative']))
                                            <div class="mt-2 text-right">
                                                <span class="text-[10px] uppercase font-bold px-1 py-0.5 rounded border border-base-content/20 text-base-content/60">
                                                    {{ $event->target == 'all' ? 'Todos' : ($event->target == 'students' ? 'Estudiantes' : 'Profesores') }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                    @endif
                @endforeach
            @endforeach
        </div>
        
        @php
            $hasEvents = collect($this->calendarGrid)->flatten(1)->filter(fn($d) => count($d['events']) > 0 && $d['isCurrentMonth'])->count() > 0;
        @endphp
        
        @if(!$hasEvents)
            <div class="text-center py-12 text-base-content/50 border-2 border-dashed border-base-300 rounded-2xl">
                <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p>No hay eventos programados para este mes.</p>
            </div>
        @endif
    </div>

    <!-- Livewire Modal Events -->
    <livewire:event-form />
</div>