<?php

namespace App\Livewire\Students;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class Search extends Component
{
    use Toast;

    public ?int $selectedUserId = null;

    public string $search = '';

    #[Computed]
    public function users()
    {
        return User::query()
            ->where('role', 'student')
            ->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('lastname', 'like', "%{$this->search}%")
                    ->orWhere('firstname', 'like', "%{$this->search}%")
                    ->orWhere('id', 'like', "%{$this->search}%");
            })
            ->orderBy('lastname')
            ->take(50)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => "{$user->lastname}, {$user->firstname} ({$user->id})",
                ];
            });
    }

    public function selectUser($id): void
    {
        $this->redirect(route('user-payments', ['user' => $id]), navigate: true);
    }

    public function render()
    {
        return view('livewire.students.search');
    }
}
