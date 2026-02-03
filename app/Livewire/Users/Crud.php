<?php

namespace App\Livewire\Users;

use App\Models\Career;
use App\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use Toast;

    public bool $drawer = false;

    public $roles = [];

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

    public $careers = [];

    public $career_id = null;

    public $user_id = null;

    public function mount($id = null)
    {
        $this->roles = User::$roles;
        $this->careers = Career::where(
            ['allow_enrollments' => true, 'allow_evaluations' => true]
        )->get();
        if ($this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }

        if ($id === null) {
            $id = session('user_id');
        }
        $this->user_id = $id;

        if ($id !== null) {
            $user = User::find($id);
            if ($user) {
                $this->data = $user->toArray();
                $this->data['careers'] = $user->careers;
            }
        }
    }

    public function save()
    {
        $data = [];
        // check if user not exists assign password
        if (! User::find($this->user_id)) {
            $data['password'] = $this->data['id'];
        }
        $data['id'] = $this->data['id'];
        $data['name'] = $this->data['name'];
        $data['email'] = $this->data['email'];
        $data['firstname'] = $this->data['firstname'];
        $data['lastname'] = $this->data['lastname'];
        $data['phone'] = $this->data['phone'];
        $data['role'] = $this->data['role'];
        $data['enabled'] = $this->data['enabled'];
        // dd($data, $this->user_id);
        User::updateOrCreate(['id' => $this->user_id], $data);
        $this->success('Usuario guardado.');
        // $this->redirect('/users');
    }

    public function changePassword()
    {
        $user = User::find($this->data['id']);
        if ($user) {
            $user->password = $this->data['password'];
            $user->save();
            $this->success('Contraseña cambiada.');
            $this->drawer = false;
            $this->skipRender();
        }
    }

    public function assignCareer()
    {
        $user = User::find($this->data['id']);
        if ($user) {
            $user->careers()->attach($this->career_id);
            $this->data['careers'] = $user->careers;
            $this->skipMount();
        }
    }

    public function removeCareer($career_id)
    {
        $user = User::find($this->data['id']);
        if ($user) {
            $user->careers()->detach($career_id);
            $this->data['careers'] = $user->careers;
            $this->skipMount();
        }
    }

    public function render()
    {
        return view('livewire.users.crud');
    }
}
