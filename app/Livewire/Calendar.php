<?php

namespace App\Livewire;

use App\Models\Career;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Lazy]
class Calendar extends Component
{
    public $date;

    public $selectedEvent = null;

    #[Url]
    public $career_id = null;

    #[Url(as: 'day')]
    public $date_string = null;

    public function mount()
    {
        $user = auth()->user();

        // Initialize date from URL or current time
        if ($this->date_string) {
            try {
                $this->date = Carbon::parse($this->date_string);
            } catch (\Exception $e) {
                $this->date = Carbon::now();
                $this->date_string = $this->date->toDateString();
            }
        } else {
            $this->date = Carbon::now();
            $this->date_string = $this->date->toDateString();
        }

        // Default career for students if not in URL
        if (! $this->career_id && $user->hasRole('student')) {
            $careers = $this->careers;
            if ($careers->isNotEmpty()) {
                $this->career_id = $careers->first()->id;
            }
        }
    }

    #[Computed]
    public function careers()
    {
        $user = auth()->user();

        return Cache::remember('user_careers_'.$user->id, 3600, function () use ($user) {
            if ($user->hasAnyRole(['admin', 'director', 'administrative'])) {
                return Career::all();
            } elseif ($user->hasRole('teacher')) {
                $subjects = $user->subjects()->with('career')->get();

                return $subjects->map(function ($subject) {
                    return $subject->career;
                })->filter()->unique('id')->values();
            } else { // student
                return $user->careers ?? collect();
            }
        });
    }

    public function previousMonth()
    {
        $this->date->subMonth();
        $this->date_string = $this->date->toDateString();
    }

    public function nextMonth()
    {
        $this->date->addMonth();
        $this->date_string = $this->date->toDateString();
    }

    #[Computed]
    public function calendarGrid()
    {
        $startDate = $this->date->copy()->firstOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endDate = $this->date->copy()->lastOfMonth()->endOfWeek(Carbon::SATURDAY);
        $user = auth()->user();

        $events = Event::with('subject', 'presidente', 'vocal1', 'vocal2')->whereBetween('start', [$startDate, $endDate])
            ->where(function ($query) use ($user) {
                // Admin, director, etc. see everything
                if ($user->hasAnyRole(['admin', 'director', 'administrative', 'principal'])) {
                    return;
                }

                $query->where('target', 'all');

                if ($user->hasRole('teacher')) {
                    $query->orWhere('target', 'teachers');
                } elseif ($user->hasRole('student')) {
                    $query->orWhere('target', 'students');
                }
            })
            ->when($this->career_id, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('subject', function ($q2) {
                        $q2->where('career_id', $this->career_id);
                    })->orWhereNull('subject_id');
                });
            }, function ($query) {
                // When no career is selected, show all events
                return $query;
            })
            ->get();

        $grid = collect();
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
            $grid->push($daysInWeek);
        }

        return $grid;
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
        unset($this->calendarGrid);

        return view('livewire.calendar');
    }
}
