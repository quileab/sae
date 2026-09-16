<?php

namespace App\Livewire\Users;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Profile extends Component
{
    use Toast, WithFileUploads;

    public $photo;

    public $name;

    public $email;

    public $firstname;

    public $lastname;

    public $phone;

    public $password;

    public $password_confirmation;

    public function mount()
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->firstname = $user->firstname;
        $this->lastname = $user->lastname;
        $this->phone = $user->phone;
    }

    public function save()
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'phone' => $data['phone'],
        ]);

        if ($this->photo) {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($this->photo->getRealPath());
            $image->cover(300, 300);
            $encoded = $image->encode(new WebpEncoder(quality: 80));

            Storage::disk('public')->put('avatars/'.$user->id.'.webp', $encoded->toString());
        }

        $this->success('Perfil actualizado correctamente.');
    }

    public function updatePassword()
    {
        $user = Auth::user();

        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->reset(['password', 'password_confirmation']);

        $this->success('Contraseña actualizada correctamente.');
    }

    public function render()
    {
        return view('livewire.users.profile');
    }
}
