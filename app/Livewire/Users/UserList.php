<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class UserList extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    public string $filterRole = '';

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $row_decoration;

    public function clear(): void
    {
        $this->reset();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function delete(User $user): void
    {
        // Solo administradores pueden eliminar usuarios
        if (! auth()->user()->hasRole('admin')) {
            $this->error('No tienes permisos para realizar esta acción.');

            return;
        }

        // No permitirse auto-eliminarse
        if ($user->id === auth()->id()) {
            $this->error('No puedes eliminar tu propia cuenta.');

            return;
        }

        DB::transaction(function () use ($user) {
            // Eliminar relaciones críticas de pagos y académico
            $user->payments()->delete();
            $user->userPayments()->delete();
            $user->grades()->delete();
            $user->enrollments()->delete();
            $user->careers()->detach();
            $user->receivedMessages()->detach();
            $user->messages()->delete();

            $user->delete();
        });

        $this->success('Usuario y toda su información relacionada eliminados permanentemente.', position: 'toast-bottom');
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'fullname', 'label' => 'Apellido y Nombre', 'class' => 'w-full'],
            ['key' => 'phone', 'label' => 'Tel.', 'sortable' => false],
            ['key' => 'email', 'label' => 'E-mail', 'sortable' => false],
            ['key' => 'role', 'label' => 'Rol', 'sortable' => false],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
