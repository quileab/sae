<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Models\Unit;
use App\Models\Topic;
use App\Models\Resource;
use Livewire\Component;
use Mary\Traits\Toast;

class ContentManager extends Component
{
    use Toast;

    public Subject $subject;
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

    public function mount(Subject $subject)
    {
        $this->subject = $subject;
    }

    public function addUnit()
    {
        $this->reset('unitForm');
        $this->editingUnit = false;
        $this->showUnitModal = true;
    }

    public function editUnit($unitId)
    {
        $unit = $this->subject->units()->findOrFail($unitId);
        $this->unitForm = $unit->toArray();
        $this->editingUnit = true;
        $this->showUnitModal = true;
    }

    public function saveUnit()
    {
        $validated = $this->validate([
            'unitForm.name' => 'required|string|max:255',
            'unitForm.description' => 'nullable|string',
            'unitForm.order' => 'required|integer',
            'unitForm.is_visible' => 'boolean',
        ]);

        $data = $validated['unitForm'];
        $data['is_visible'] = filter_var($data['is_visible'], FILTER_VALIDATE_BOOLEAN);

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
        $this->reset('topicForm');
        $this->topicForm['unit_id'] = $unitId;
        $this->editingTopic = false;
        $this->showTopicModal = true;
    }

    public function editTopic($topicId)
    {
        $topic = Topic::findOrFail($topicId);
        $this->topicForm = $topic->toArray();
        $this->editingTopic = true;
        $this->showTopicModal = true;
    }

    public function saveTopic()
    {
        $validated = $this->validate([
            'topicForm.unit_id' => 'required|exists:units,id',
            'topicForm.name' => 'required|string|max:255',
            'topicForm.content' => 'nullable|string',
            'topicForm.order' => 'required|integer',
            'topicForm.is_visible' => 'boolean',
        ]);

        $data = $validated['topicForm'];
        $data['is_visible'] = filter_var($data['is_visible'], FILTER_VALIDATE_BOOLEAN);

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
        $this->reset('resourceForm');
        $this->resourceForm['topic_id'] = $topicId;
        $this->editingResource = false;
        $this->showResourceModal = true;
    }

    public function editResource($resourceId)
    {
        $resource = Resource::findOrFail($resourceId);
        $this->resourceForm = $resource->toArray();
        $this->editingResource = true;
        $this->showResourceModal = true;
    }

    public function saveResource()
    {
        $validated = $this->validate([
            'resourceForm.topic_id' => 'required|exists:topics,id',
            'resourceForm.title' => 'required|string|max:255',
            'resourceForm.url' => 'required|url|max:255',
            'resourceForm.is_visible' => 'boolean',
        ]);

        $data = $validated['resourceForm'];
        $data['is_visible'] = filter_var($data['is_visible'], FILTER_VALIDATE_BOOLEAN);

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
        Resource::findOrFail($resourceId)->delete();
        $this->success('Recurso eliminado correctamente.');
        $this->subject->refresh();
    }

    public function toggleUnitVisibility($unitId)
    {
        $unit = Unit::find($unitId);
        if ($unit) {
            $unit->is_visible = !$unit->is_visible;
            $unit->save();
            $this->subject->refresh();
            $this->success('Visibilidad de la unidad actualizada.');
        }
    }

    public function toggleTopicVisibility($topicId)
    {
        $topic = Topic::find($topicId);
        if ($topic) {
            $topic->is_visible = !$topic->is_visible;
            $topic->save();
            $this->subject->refresh();
            $this->success('Visibilidad del tema actualizada.');
        }
    }

    public function toggleResourceVisibility($resourceId)
    {
        $resource = Resource::find($resourceId);
        if ($resource) {
            $resource->is_visible = !$resource->is_visible;
            $resource->save();
            $this->subject->refresh();
            $this->success('Visibilidad del recurso actualizada.');
        }
    }

    public function render()
    {
        return view('livewire.content-manager');
    }
}