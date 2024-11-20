<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'admin', 'description' => 'Administrador del sistema']);
        Role::create(['name' => 'student', 'description' => 'Estudiante normal']);
        Role::create(['name' => 'teacher', 'description' => 'Profesor']);
        Role::create(['name' => 'director', 'description' => 'Director con permisos ampliados']);
        Role::create(['name' => 'administrative', 'description' => 'Administrativo']);
        Role::create(['name' => 'treasurer', 'description' => 'Tesorero']);
        Role::create(['name' => 'basic_user', 'description' => 'Usuario con permisos básicos']);
    }
}
