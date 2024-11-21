<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

// Create or Update User information
new class extends Component {
    use Toast;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $firstname = '';
    public string $lastname = '';
    public string $phone = '';
    public string $role = '';


}; ?>

<div>
</div>

