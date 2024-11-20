<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'approve_class', 'description' => 'Aprobar clases observadas']);
        Permission::create(['name' => 'manage_users', 'description' => 'Administrar usuarios']);
        Permission::create(['name' => 'view_payments', 'description' => 'Ver pagos']);
        Permission::create(['name' => 'manage_library', 'description' => 'Administrar biblioteca']);
    }
}
