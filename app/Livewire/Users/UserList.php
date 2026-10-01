<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class UserList extends Component
{
    use Toast, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filterRole = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $row_decoration;

    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'fullname', 'label' => 'Apellido y Nombre', 'class' => 'w-full'],
            ['key' => 'phone', 'label' => 'Tel.', 'sortable' => false],
            ['key' => 'email', 'label' => 'E-mail', 'sortable' => false],
            ['key' => 'role', 'label' => 'Rol', 'sortable' => false],
            ['key' => 'status', 'label' => 'Estado', 'sortable' => false],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function statuses(): array
    {
        return UserStatus::options();
    }

    public function users()
    {
        $column = $this->sortBy['column'];
        $direction = $this->sortBy['direction'];

        $query = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('firstname', 'like', "%{$this->search}%")
                        ->orWhere('lastname', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('id', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterRole, function ($query) {
                $query->where('role', $this->filterRole);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            });

        if ($column === 'fullname') {
            $query->orderBy('lastname', $direction)
                ->orderBy('firstname', $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        return $query->paginate(20);
    }

    public function selectForEnrollment($id): void
    {
        $this->redirect('/enrollments?user_id='.$id);
    }

    public function render()
    {
        $row_decoration = [
            'text-red-500' => fn (User $user) => $user->enabled === false,
        ];

        return view('livewire.users.user-list', [
            'users' => $this->users(),
            'headers' => $this->headers(),
            'row_decoration' => $row_decoration,
        ]);
    }
}
