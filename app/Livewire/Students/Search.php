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
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => "{$user->lastname}, {$user->firstname} ({$user->id})",
                ];
            });
    }

    public function searchUsers(string $value = ''): void
    {
        $this->search = $value;
    }

    public function updatedSelectedUserId($value): void
    {
        if ($value) {
            $this->redirect(route('user-payments', ['user' => $value]), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.students.search');
    }
}
