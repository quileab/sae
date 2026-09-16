<?php

namespace App\Livewire\Users;

use App\Models\Career;
use App\Models\JustifiedAbsence;
use App\Models\User;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class UserForm extends Component
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

    public array $justifiedAbsences = [];

    public string $justificationStartDate = '';

    public string $justificationEndDate = '';

    public string $justificationDescription = '';

    public function mount($id = null)
    {
        $this->authorizeStaff();

        $this->user_id = $id;

        if ($id !== null) {
            $user = User::findOrFail($id);
            $this->data = $user->toArray();
            $this->data['careers'] = $user->careers;
            $this->loadJustifiedAbsences();
        }

        if ($this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }
    }

    #[Computed]
    public function roles()
    {
        return User::roleOptions();
    }

    #[Computed]
    public function avatarUrl(): string
    {
        if (! empty($this->data['id']) && Storage::disk('public')->exists('avatars/'.$this->data['id'].'.webp')) {
            return asset('storage/avatars/'.$this->data['id'].'.webp').'?v='.Storage::disk('public')->lastModified('avatars/'.$this->data['id'].'.webp');
        }

        $name = $this->data['name'] ?? 'Usuario';

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    #[Computed]
    public function careers()
    {
        return Career::where(['allow_enrollments' => true, 'allow_evaluations' => true])->get();
    }

    public function save()
    {
        $this->authorizeStaff();

        $this->validate([
            'data.id' => 'required|integer|unique:users,id,'.($this->user_id ?? 'NULL'),
            'data.name' => 'required|string|max:255',
            'data.email' => 'required|email|max:255|unique:users,email,'.($this->user_id ?? 'NULL'),
            'data.firstname' => 'required|string|max:255',
            'data.lastname' => 'required|string|max:255',
            'data.phone' => 'nullable|string|max:255',
            'data.role' => 'required|string|in:admin,principal,director,administrative,preceptor,treasurer,teacher,student,basic_user',
            'data.enabled' => 'required|boolean',
        ], [
            'data.id.required' => 'El ID es obligatorio.',
            'data.id.integer' => 'El ID debe ser un número entero.',
            'data.id.unique' => 'El ID ya está en uso por otro usuario.',
            'data.name.required' => 'El ID Name es obligatorio.',
            'data.email.required' => 'El correo electrónico es obligatorio.',
            'data.email.email' => 'El correo electrónico debe ser válido.',
            'data.email.unique' => 'El correo electrónico ya está registrado.',
            'data.firstname.required' => 'El nombre es obligatorio.',
            'data.lastname.required' => 'El apellido es obligatorio.',
            'data.role.required' => 'El rol es obligatorio.',
            'data.role.in' => 'El rol seleccionado no es válido.',
        ]);

        // If we are editing and the ID has changed, update it directly via query builder
        if ($this->user_id && $this->data['id'] != $this->user_id) {
            if (User::where('id', $this->data['id'])->exists()) {
                $this->error('El nuevo ID ya está en uso por otro usuario.');

                return;
            }

            DB::table('users')
                ->where('id', $this->user_id)
                ->update(['id' => $this->data['id']]);

            $this->user_id = $this->data['id'];
        }

        $user = User::find($this->user_id) ?? new User;

        $user->forceFill([
            'id' => $this->data['id'],
            'name' => $this->data['name'],
            'email' => $this->data['email'],
            'firstname' => $this->data['firstname'],
            'lastname' => $this->data['lastname'],
            'phone' => $this->data['phone'],
            'role' => $this->data['role'],
            'enabled' => $this->data['enabled'],
        ]);

        if (! $user->exists) {
            $user->password = $this->data['id'];
        }

        $user->save();
        $this->user_id = $user->id;

        $this->success('Usuario guardado.');
    }

    public function changePassword()
    {
        $this->authorizeStaff();

        $this->validate([
            'data.password' => 'required|string|min:4',
        ], [
            'data.password.required' => 'La contraseña es obligatoria.',
            'data.password.min' => 'La contraseña debe tener al menos 4 caracteres.',
        ]);

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

    public function loadJustifiedAbsences(): void
    {
        if ($this->user_id) {
            $this->justifiedAbsences = JustifiedAbsence::where('user_id', $this->user_id)
                ->orderBy('start_date', 'desc')
                ->get()
                ->toArray();
        } else {
            $this->justifiedAbsences = [];
        }
    }

    public function addJustifiedAbsence(): void
    {
        $this->authorizeStaff();

        $this->validate([
            'justificationStartDate' => 'required|date',
            'justificationEndDate' => 'required|date|after_or_equal:justificationStartDate',
            'justificationDescription' => 'required|string|max:255',
        ], [
            'justificationStartDate.required' => 'La fecha de inicio es obligatoria.',
            'justificationEndDate.required' => 'La fecha de fin es obligatoria.',
            'justificationEndDate.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la de inicio.',
            'justificationDescription.required' => 'La descripción es obligatoria.',
        ]);

        JustifiedAbsence::create([
            'user_id' => $this->user_id,
            'start_date' => $this->justificationStartDate,
            'end_date' => $this->justificationEndDate,
            'description' => $this->justificationDescription,
        ]);

        $this->reset(['justificationStartDate', 'justificationEndDate', 'justificationDescription']);
        $this->loadJustifiedAbsences();
        $this->success('Justificación guardada.');
    }

    public function deleteJustifiedAbsence(int $id): void
    {
        $this->authorizeStaff();

        $ja = JustifiedAbsence::where('user_id', $this->user_id)->findOrFail($id);
        $ja->delete();

        $this->loadJustifiedAbsences();
        $this->success('Justificación eliminada.');
    }

    public function render()
    {
        return view('livewire.users.user-form');
    }
}
