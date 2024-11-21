<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'id' => 1,
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'phone' => '1234567890',
            'enabled' => 1,
            'role' => 'admin',
            'firstname' => 'admin',
            'lastname' => 'admin',
            'password' => 'admin123',
            //'password'=>'$12$8M/ERONr0BR6oIaMVULnZenvM7kPV9rOBMh7j9hnxckas00VhDEc.'

        ]);

        //Call RoleSeeder
        $this->call(RoleSeeder::class);
    }
}
