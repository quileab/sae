<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Models\Unit;
use Livewire\Component;

class SimplifiedContent extends Component
{
    public Subject $subject;

    public $units;

    public function mount(Subject $subject): void
    {
        $this->subject = $subject;
        $this->units = Unit::where('subject_id', $this->subject->id)
            ->where('is_visible', true)
            ->with([
                'topics' => function ($query) {
                    $query->where('is_visible', true)->with([
                        'resources' => function ($query) {
                            $query->where('is_visible', true);
                        },
                    ]);
                },
            ])
            ->get();
    }

    public function render()
    {
        return view('livewire.simplified-content');
    }
}
