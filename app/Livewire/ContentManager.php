<?php

namespace App\Livewire;

use App\Models\Resource;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class ContentManager extends Component
{
    use AuthorizesAccess;
    use Toast;
    use WithFileUploads;

    public bool $showUnitModal = false;

    public bool $editingUnit = false;

    public array $unitForm = [
        'id' => null,
        'name' => '',
        'description' => '',
        'order' => 0,
        'is_visible' => true,
    ];

    public ?int $selectedUnitId = null;

    public bool $showTopicModal = false;

    public bool $editingTopic = false;

    public array $topicForm = [
        'id' => null,
        'unit_id' => null,
        'name' => '',
        'content' => '',
        'order' => 0,
        'is_visible' => true,
    ];

    public ?int $selectedTopicId = null;

    public bool $showResourceModal = false;

    public bool $editingResource = false;

    public array $resourceForm = [
        'id' => null,
        'topic_id' => null,
        'title' => '',
        'url' => '',
        'is_visible' => true,
    ];

    public $upload;

    public bool $isStudent = false;

    public Subject $subject;

    public $subject_id;

    #[Computed]
    public function subjects()
    {
        return auth()->user()->subjects;
    }

    public function mount($subject = null)
    {
        $user = auth()->user();

        // Si recibimos 0 o null (desde el menú lateral), intentamos resolver la materia
        if (! $subject || (is_numeric($subject) && $subject == 0) || (is_object($subject) && ! $subject->exists)) {
            $sid = session('current_subject_id') ?? ($user->subjects->first()->id ?? null);

            if (! $sid) {
                $this->error('Debe seleccionar una materia primero.');

                return $this->redirect('/class-sessions', navigate: true);
            }

            $this->subject = Subject::findOrFail($sid);
        } else {
            // Caso normal: subject inyectado por Route Binding
            $this->subject = $subject;
        }

        $this->isStudent = auth()->user()->hasRole('student');
        $this->subject_id = $this->subject->id;
        session()->put('current_subject_id', $this->subject_id);
        $this->authorizeSubject($this->subject_id);
    }

    public function updatedSubjectId($value)
    {
        if ($value) {
            session()->put('current_subject_id', $value);

            return $this->redirect('/subjects-content/'.$value, navigate: true);
        }
    }

    public function addUnit()
    {
        $this->authorizeStaff();
        $this->reset('unitForm');
        $this->editingUnit = false;
        $this->showUnitModal = true;
    }

    public function editUnit($unitId)
    {
        $this->authorizeStaff();
        $unit = $this->subject->units()->findOrFail($unitId);
        $this->unitForm = $unit->toArray();
        $this->editingUnit = true;
        $this->showUnitModal = true;
    }

    public function saveUnit()
    {
        $this->authorizeStaff();
        $validated = $this->validate([
            'unitForm.name' => 'required|string|max:255',
            'unitForm.description' => 'nullable|string',
            'unitForm.order' => 'required|integer',
            'unitForm.is_visible' => 'boolean',
        ]);

        $data = $validated['unitForm'];

        if ($this->editingUnit) {
            $unit = $this->subject->units()->findOrFail($this->unitForm['id']);
            $unit->update($data);
            $this->success('Unidad actualizada correctamente.');
        } else {
            $this->subject->units()->create($data);
            $this->success('Unidad creada correctamente.');
        }

        $this->showUnitModal = false;
        $this->subject->refresh(); // Refresh the subject to get the latest units
    }

    public function deleteUnit($unitId)
    {
        $this->authorizeStaff();
        $this->subject->units()->findOrFail($unitId)->delete();
        $this->success('Unidad eliminada correctamente.');
        $this->subject->refresh();
    }

    public function toggleTopics($unitId)
    {
        $this->selectedUnitId = ($this->selectedUnitId == $unitId) ? null : $unitId;
    }

    public function addTopic($unitId)
    {
        $this->authorizeStaff();
        $this->reset('topicForm');
        $this->topicForm['unit_id'] = $unitId;
        $this->editingTopic = false;
        $this->showTopicModal = true;
    }

    public function editTopic($topicId)
    {
        $this->authorizeStaff();
        $topic = Topic::findOrFail($topicId);
        $this->topicForm = $topic->toArray();
        $this->editingTopic = true;
        $this->showTopicModal = true;
    }

    public function saveTopic()
    {
        $this->authorizeStaff();
        $validated = $this->validate([
            'topicForm.unit_id' => 'required|exists:units,id',
            'topicForm.name' => 'required|string|max:255',
            'topicForm.content' => 'nullable|string',
            'topicForm.order' => 'required|integer',
            'topicForm.is_visible' => 'boolean',
        ]);

        $data = $validated['topicForm'];

        if ($this->editingTopic) {
            $topic = Topic::findOrFail($this->topicForm['id']);
            $topic->update($data);
            $this->success('Tema actualizado correctamente.');
        } else {
            Unit::findOrFail($this->topicForm['unit_id'])->topics()->create($data);
            $this->success('Tema creado correctamente.');
        }

        $this->showTopicModal = false;
        $this->subject->refresh(); // Refresh the subject to get the latest units and topics
    }

    public function deleteTopic($topicId)
    {
        $this->authorizeStaff();
        Topic::findOrFail($topicId)->delete();
        $this->success('Tema eliminado correctamente.');
        $this->subject->refresh();
    }

    public function toggleResources($topicId)
    {
        $this->selectedTopicId = ($this->selectedTopicId == $topicId) ? null : $topicId;
    }

    public function addResource($topicId)
    {
        $this->authorizeStaff();
        $this->reset('resourceForm');
        $this->resourceForm['topic_id'] = $topicId;
        $this->editingResource = false;
        $this->showResourceModal = true;
    }

    public function editResource($resourceId)
    {
        $this->authorizeStaff();
        $resource = Resource::findOrFail($resourceId);
        $this->resourceForm = $resource->toArray();
        $this->editingResource = true;
        $this->showResourceModal = true;
    }

    public function saveResource()
    {
        $this->authorizeStaff();
        $validated = $this->validate([
            'resourceForm.topic_id' => 'required|exists:topics,id',
            'resourceForm.title' => 'required|string|max:255',
            'resourceForm.url' => 'required|url|max:255',
            'resourceForm.is_visible' => 'boolean',
        ]);

        $data = $validated['resourceForm'];

        if ($this->editingResource) {
            $resource = Resource::findOrFail($this->resourceForm['id']);
            $resource->update($data);
            $this->success('Recurso actualizado correctamente.');
        } else {
            Topic::findOrFail($this->resourceForm['topic_id'])->resources()->create($data);
            $this->success('Recurso creado correctamente.');
        }

        $this->showResourceModal = false;
        $this->subject->refresh(); // Refresh the subject to get the latest units, topics, and resources
    }

    public function deleteResource($resourceId)
    {
        $this->authorizeStaff();
        Resource::findOrFail($resourceId)->delete();
        $this->success('Recurso eliminado correctamente.');
        $this->subject->refresh();
    }

    public function toggleVisibility(string $type, int $id): void
    {
        $this->authorizeStaff();

        $model = match ($type) {
            'unit' => Unit::find($id),
            'topic' => Topic::find($id),
            'resource' => Resource::find($id),
        };

        if ($model) {
            $model->update(['is_visible' => ! $model->is_visible]);
            $this->subject->refresh();
            $this->success('Visibilidad actualizada.');
        }
    }

    public function exportContent()
    {
        $this->authorizeStaff();
        $units = $this->subject->units()->with(['topics.resources'])->get();

        $fileName = 'content-'.$this->subject->id.'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($units) {
            echo json_encode($units, JSON_PRETTY_PRINT);
        }, $fileName);
    }

    public function importContent()
    {
        $this->authorizeStaff();
        $this->validate([
            'upload' => 'required|file|mimes:json|max:10240', // 10MB Max
        ]);

        $content = $this->upload->get();
        $units = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error al decodificar el archivo JSON.');

            return;
        }

        DB::transaction(function () use ($units) {
            // Delete existing content
            foreach ($this->subject->units as $unit) {
                $unit->delete(); // This will trigger deleting events on the model for topics and resources
            }

            foreach ($units as $unitData) {
                $unit = $this->subject->units()->create([
                    'name' => $unitData['name'],
                    'description' => $unitData['description'],
                    'order' => $unitData['order'],
                    'is_visible' => $unitData['is_visible'],
                ]);

                if (isset($unitData['topics'])) {
                    foreach ($unitData['topics'] as $topicData) {
                        $topic = $unit->topics()->create([
                            'name' => $topicData['name'],
                            'content' => $topicData['content'],
                            'order' => $topicData['order'],
                            'is_visible' => $topicData['is_visible'],
                        ]);

                        if (isset($topicData['resources'])) {
                            foreach ($topicData['resources'] as $resourceData) {
                                $topic->resources()->create([
                                    'title' => $resourceData['title'],
                                    'url' => $resourceData['url'],
                                    'is_visible' => $resourceData['is_visible'],
                                ]);
                            }
                        }
                    }
                }
            }
        });

        $this->success('Contenido importado correctamente.');
        $this->subject->refresh();
        $this->reset('upload');
    }

    public function updatedUpload()
    {
        $this->importContent();
    }

    public function render()
    {
        $colors = [
            'border-l-violet-500', 'border-l-blue-500', 'border-l-emerald-500',
            'border-l-amber-500', 'border-l-orange-500', 'border-l-red-500',
        ];

        $bgColors = [
            'bg-violet-500', 'bg-blue-500', 'bg-emerald-500',
            'bg-amber-500', 'bg-orange-500', 'bg-red-500',
        ];

        return view('livewire.content-manager', compact('colors', 'bgColors'));
    }
}
