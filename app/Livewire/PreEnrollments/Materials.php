<?php

namespace App\Livewire\PreEnrollments;

use App\Models\Career;
use App\Models\PreEnrollmentMaterial;
use App\Services\AcademicCycle;
use Livewire\Component;
use Livewire\WithFileUploads;

class Materials extends Component
{
    use WithFileUploads;

    public $title = '';

    public $career_id = '';

    public $cycle;

    public $file;

    public function mount(): void
    {
        $this->cycle = AcademicCycle::enrollmentCycle();
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:150',
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,zip',
        ]);

        $path = $this->file->store('pre_enrollment_materials', 'public');

        PreEnrollmentMaterial::create([
            'cycle_id' => $this->cycle,
            'career_id' => $this->career_id ?: null,
            'title' => $this->title,
            'file_path' => $path,
            'original_name' => $this->file->getClientOriginalName(),
        ]);

        $this->reset(['title', 'file', 'career_id']);
        session()->flash('success', 'Material subido.');
    }

    public function delete(int $id): void
    {
        $m = PreEnrollmentMaterial::findOrFail($id);
        \Storage::disk('public')->delete($m->file_path);
        $m->delete();
    }

    public function render()
    {
        return view('livewire.pre-enrollments.materials', [
            'materials' => PreEnrollmentMaterial::with('career')->where('cycle_id', $this->cycle)->latest()->get(),
            'careers' => Career::orderBy('name')->get(),
        ]);
    }
}
