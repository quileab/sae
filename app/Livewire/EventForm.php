<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Models\Event;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class EventForm extends Component
{
    public $title;
    public $start;
    public $end;
    public $color = '#0C4767';
    public $subject_id;
    public $careers;
    public $subjects;
    public $eventId = null;
    public $isReadOnly = false;

    #[On('openEventModal')]
    public function openEventModal($eventId = null, $date = null)
    {
        $this->reset();
        $this->careers = Auth::user()->careers ?? collect();
        $this->subjects = collect();

        if ($this->careers->count() == 1) {
            $this->career_id = $this->careers->first()->id;
            $this->updatedCareerId($this->career_id);
        }

        if ($eventId) {
            $this->eventId = $eventId;
            $event = Event::find($eventId);
            if ($event) {
                if ($event->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
                    $this->isReadOnly = true;
                }

                $this->title = $event->title;
                $this->start = $event->start->format('Y-m-d\\TH:i');
                $this->end = $event->end->format('Y-m-d\\TH:i');
                $this->color = $event->color;
                $this->subject_id = $event->subject_id;
                if ($this->subject_id) {
                    $this->career_id = $event->subject->career_id;
                    $this->subjects = Auth::user()->subjects()->with('career')->where('career_id', $this->career_id)->get()->map(function ($subject) {
                        return [
                            'id' => $subject->id,
                            'name' => $subject->id . ' - ' . $subject->name,
                        ];
                    }) ?? collect();
                }
            }
        } elseif ($date) {
            $this->start = $date . 'T08:00';
            $this->end = $date . 'T09:00';
        }
        $this->showModal = true;
    }

    public $career_id;
    public $showModal = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'start' => 'required|date',
        'end' => 'required|date|after_or_equal:start',
        'color' => 'required|string|max:7',
        'subject_id' => 'nullable|exists:subjects,id',
    ];

    public function mount()
    {
        $this->careers = collect();
        $this->subjects = collect();
    }

    public function updatedCareerId($value)
    {
        $this->subjects = Auth::user()->subjects()->with('career')->where('career_id', $value)->get()->map(function ($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->id . ' - ' . $subject->name,
            ];
        }) ?? collect();
    }

    #[On('closeEventModal')]
    public function closeEventModal()
    {
        $this->showModal = false;
        $this->reset();
    }

    public function save()
    {
        $this->validate();

        if ($this->eventId) {
            $event = Event::find($this->eventId);
            if ($event && $event->user_id !== Auth::id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        Event::updateOrCreate(
            ['id' => $this->eventId],
            [
                'title' => $this->title,
                'start' => $this->start,
                'end' => $this->end,
                'color' => $this->color,
                'user_id' => Auth::id(),
                'subject_id' => $this->subject_id,
            ]
        );

        $this->dispatch('eventSaved');
        $this->dispatch('close-event-modal');
    }

    public function delete()
    {
        $event = Event::find($this->eventId);
        if ($event && $event->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($event) {
            $event->delete();
        }

        $this->dispatch('eventSaved');
        $this->dispatch('closeEventModal');
    }

    public function closeModal()
    {
        $this->dispatch('closeEventModal');
    }

    public function render()
    {
        return view('livewire.event-form');
    }
}
