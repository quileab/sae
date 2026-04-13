<?php

namespace App\Livewire\Users;

use App\Models\Career;
use App\Models\User;
use App\Traits\AuthorizesAccess;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use AuthorizesAccess, Toast;

    public bool $drawer = false;

    public $data = [
        'id' => null,
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
        'firstname' => '',
        'lastname' => '',
        'phone' => '',
        'role' => 'student',
        'enabled' => true,
        'careers' => [],
    ];

    public $career_id = null;

    public $user_id = null;

    public function mount($id = null)
    {
        $this->authorizeStaff();

        $this->user_id = $id;

        if ($id !== null) {
            $user = User::findOrFail($id);
            $this->data = $user->toArray();
            $this->data['careers'] = $user->careers;
        }

        if ($this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }
    }

    #[Computed]
    public function roles()
    {
        return User::$roles;
    }

    #[Computed]
    public function careers()
    {
        return Career::where(['allow_enrollments' => true, 'allow_evaluations' => true])->get();
    }

    public function save()
    {
        $this->authorizeStaff();

        $saveData = [
            'id' => $this->data['id'],
            'name' => $this->data['name'],
            'email' => $this->data['email'],
            'firstname' => $this->data['firstname'],
            'lastname' => $this->data['lastname'],
            'phone' => $this->data['phone'],
            'role' => $this->data['role'],
            'enabled' => $this->data['enabled'],
        ];

        if (! User::find($this->user_id)) {
            $saveData['password'] = $this->data['id'];
        }

        User::updateOrCreate(['id' => $this->user_id], $saveData);
        $this->success('Usuario guardado.');
    }

    public function changePassword()
    {
        $this->authorizeStaff();

        $user = User::findOrFail($this->data['id']);
        $user->password = $this->data['password'];
        $user->save();

        $this->success('Contraseña cambiada.');
        $this->drawer = false;
    }

    public function assignCareer()
    {
        $this->authorizeStaff();

        $user = User::findOrFail($this->data['id']);
        $user->careers()->syncWithoutDetaching([$this->career_id]);
        $this->data['careers'] = $user->careers()->get();
    }

    public function removeCareer($career_id)
    {
        $this->authorizeStaff();

        $user = User::findOrFail($this->data['id']);
        $user->careers()->detach($career_id);
        $this->data['careers'] = $user->careers()->get();
    }

    public function delete(): void
    {
        $this->authorizeStaff();

        $user = User::findOrFail($this->data['id']);
        $user->delete();

        $this->success('Usuario eliminado.');
        $this->redirect('/users', navigate: true);
    }

    public function render()
    {
        return view('livewire.users.crud');
    }
}
