<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Models\Event;
use App\Models\User;
use App\Models\Career;
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
    public $description;
    public $presidente_id;
    public $vocal1_id;
    public $vocal2_id;
    public $teachers;

    public $careers;
    public $subjects;
    public $eventId = null;
    public $isReadOnly = false;

    #[On('openEventModal')]
    public function openEventModal($eventId = null, $date = null)
    {
        $this->reset();
        $user = Auth::user();
        if ($user->hasAnyRole(['admin', 'director', 'administrative'])) {
            $this->careers = Career::all();
        } elseif ($user->hasRole('teacher')) {
            $subjects = $user->subjects()->with('career')->get();
            $this->careers = $subjects->map(function ($subject) {
                return $subject->career;
            })->filter()->unique('id')->values();
        } else { // student
            $this->careers = $user->careers ?? collect();
        }
        $this->subjects = collect();
        $this->teachers = User::where('role', 'teacher')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->lastname . ', ' . $user->firstname,
                ];
            });

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
                $this->description = $event->description;
                $this->presidente_id = $event->presidente_id;
                $this->vocal1_id = $event->vocal1_id;
                $this->vocal2_id = $event->vocal2_id;

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
        'description' => 'nullable|string',
        'presidente_id' => 'nullable|exists:users,id',
        'vocal1_id' => 'nullable|exists:users,id',
        'vocal2_id' => 'nullable|exists:users,id',
    ];

    public function mount()
    {
        $this->careers = collect();
        $this->subjects = collect();
        $this->teachers = collect();
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

        $data = [
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'color' => $this->color,
            'user_id' => Auth::id(),
            'subject_id' => $this->subject_id,
            'description' => $this->description,
        ];

        if (Auth::user()->hasAnyRole(['admin', 'director', 'administrative'])) {
            $data['presidente_id'] = $this->presidente_id;
            $data['vocal1_id'] = $this->vocal1_id;
            $data['vocal2_id'] = $this->vocal2_id;
        }

        Event::updateOrCreate(
            ['id' => $this->eventId],
            $data
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

    public function duplicate()
    {
        $this->eventId = null;
        $this->title = $this->title . ' (Copia)';
    }

    public function render()
    {
        return view('livewire.event-form');
    }
}
