<?php

namespace App\Livewire;

use App\Models\Event;
use Carbon\Carbon;
use Livewire\Component;

use Livewire\Attributes\On;

class Calendar extends Component
{
    public $date;
    public $calendarGrid;
    public $selectedEvent = null;
    public $career_id;
    public $careers;

    public function mount()
    {
        $this->date = Carbon::now();
        if (auth()->user()->hasRole('student')) {
            $this->careers = auth()->user()->careers;
            $this->career_id = auth()->user()->careers->first()->id;
        } else {
            $this->careers = auth()->user()->careers;
        }
    }

    public function previousMonth()
    {
        $this->date->subMonth();
    }

    public function nextMonth()
    {
        $this->date->addMonth();
    }

    public function generateCalendar()
    {
        $startDate = $this->date->copy()->firstOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endDate = $this->date->copy()->lastOfMonth()->endOfWeek(Carbon::SATURDAY);

        $events = Event::with('subject')->whereBetween('start', [$startDate, $endDate])
            ->when($this->career_id, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('subject', function ($q2) {
                        $q2->where('career_id', $this->career_id);
                    })->orWhereNull('subject_id');
                });
            }, function ($query) {
                // When no career is selected, show only events without a subject
                $query->whereNull('subject_id');
            })
            ->get();

        $this->calendarGrid = collect();
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $daysInWeek = collect();
            for ($i = 0; $i < 7; $i++) {
                $daysInWeek->push([
                    'day' => $currentDate->day,
                    'date' => $currentDate->toDateString(),
                    'isCurrentMonth' => $currentDate->month == $this->date->month,
                    'events' => $events->filter(function ($event) use ($currentDate) {
                        return Carbon::parse($event->start)->isSameDay($currentDate);
                    }),
                ]);
                $currentDate->addDay();
            }
            $this->calendarGrid->push($daysInWeek);
        }
    }

    public function eventClick($eventId)
    {
        $this->dispatch('openEventModal', eventId: $eventId);
    }

    public function dateClick($date)
    {
        $this->dispatch('openEventModal', eventId: null, date: $date);
    }

    public function closeModal()
    {
        $this->dispatch('closeEventModal');
    }

    #[On('eventSaved')]
    public function render()
    {
        $this->generateCalendar();
        return view('livewire.calendar');
    }
}
