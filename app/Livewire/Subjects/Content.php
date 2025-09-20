<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use App\Models\Unit;
use App\Models\Topic;
use App\Models\Resource;
use Livewire\Component;
use Mary\Traits\Toast;

class Content extends Component
{
    use Toast;

    public Subject $subject;

    // Modals
    public bool $unitModal = false;
    public bool $topicModal = false;
    public bool $resourceModal = false;

    // Forms
    public array $unitForm = [
        'is_visible' => true,
    ];
    public array $topicForm = [
        'is_visible' => true,
    ];
    public array $resourceForm = [
        'is_visible' => true,
    ];

    public ?Topic $selectedTopic = null;

    public function mount(Subject $subject)
    {
        $this->subject = $subject;
    }

    public function render()
    {
        $units = $this->subject->units()->with('topics.resources')->orderBy('order')->get();
        return view('livewire.subjects.content', ['units' => $units]);
    }

    // Unit Methods
    public function openUnitModal()
    {
        $this->reset('unitForm');
        $this->unitModal = true;
    }

    public function closeUnitModal()
    {
        $this->unitModal = false;
        $this->reset('unitForm');
    }

    public function saveUnit()
    {
        $validated = validator($this->unitForm, [
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'is_visible' => 'boolean',
        ])->validate();

        if (isset($this->unitForm['id'])) {
            $unit = Unit::find($this->unitForm['id']);
            $unit->update($validated);
            $this->success('Unidad actualizada con éxito.');
        } else {
            $this->subject->units()->create($validated);
            $this->success('Unidad creada con éxito.');
        }

        $this->closeUnitModal();
    }

    public function editUnit($id)
    {
        $unit = Unit::find($id);
        $this->unitForm = $unit->toArray();
        $this->unitModal = true;
    }

    public function deleteUnit($id)
    {
        $unit = Unit::find($id);
        $unit->delete();
        $this->success('Unidad eliminada con éxito.');
    }

    // Topic Methods
    public function openTopicModal($unitId)
    {
        $this->reset('topicForm');
        $this->topicForm['unit_id'] = $unitId;
        $this->topicModal = true;
    }

    public function closeTopicModal()
    {
        $this->topicModal = false;
        $this->reset('topicForm');
    }

    public function saveTopic()
    {
        $validated = validator($this->topicForm, [
            'unit_id' => 'required|exists:units,id',
            'name' => 'required|string|max:255',
            'content' => 'nullable|string',
            'order' => 'required|integer',
            'is_visible' => 'boolean',
        ])->validate();

        if (isset($this->topicForm['id'])) {
            $topic = Topic::find($this->topicForm['id']);
            $topic->update($validated);
            $this->success('Tema actualizado con éxito.');
        } else {
            Topic::create($validated);
            $this->success('Tema creado con éxito.');
        }

        $this->closeTopicModal();
    }

    public function editTopic($id)
    {
        $topic = Topic::find($id);
        $this->topicForm = $topic->toArray();
        $this->topicModal = true;
    }

    public function deleteTopic($id)
    {
        $topic = Topic::find($id);
        $topic->delete();
        $this->success('Tema eliminado con éxito.');
    }

    // Resource Methods
    public function openResourceModal($topicId)
    {
        $this->reset('resourceForm');
        $this->selectedTopic = Topic::with('resources')->find($topicId);
        $this->resourceForm['topic_id'] = $topicId;
        $this->resourceModal = true;
    }

    public function closeResourceModal()
    {
        $this->resourceModal = false;
        $this->reset('resourceForm');
        $this->selectedTopic = null;
    }

    public function saveResource()
    {
        $validated = validator($this->resourceForm, [
            'topic_id' => 'required|exists:topics,id',
            'title' => 'required|string|max:255',
            'url' => 'required|url',
            'is_visible' => 'boolean',
        ])->validate();

        Resource::create($validated);
        $this->success('Recurso guardado con éxito.');
        $this->selectedTopic->load('resources'); // Refresh the resources
        $this->reset('resourceForm');
        $this->resourceForm['topic_id'] = $this->selectedTopic->id;
    }

    public function deleteResource($resourceId)
    {
        $resource = Resource::find($resourceId);
        $resource->delete();
        $this->success('Recurso eliminado con éxito.');
        $this->selectedTopic->load('resources'); // Refresh the resources
    }

    // Toggle Visibility Methods
    public function toggleUnitVisibility($unitId)
    {
        $unit = Unit::find($unitId);
        $unit->is_visible = !$unit->is_visible;
        $unit->save();
        $this->success('Visibilidad de unidad actualizada.');
    }

    public function toggleTopicVisibility($topicId)
    {
        $topic = Topic::find($topicId);
        $topic->is_visible = !$topic->is_visible;
        $topic->save();
        $this->success('Visibilidad de tema actualizada.');
    }

    public function toggleResourceVisibility($resourceId)
    {
        $resource = Resource::find($resourceId);
        $resource->is_visible = !$resource->is_visible;
        $resource->save();
        $this->success('Visibilidad de recurso actualizada.');
        $this->selectedTopic->load('resources'); // Refresh the resources
    }
}