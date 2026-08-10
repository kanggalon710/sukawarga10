<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'user_id' => 'admin_id_01',
            'namaLengkap' => 'Administrator',
            'username' => 'admin',
            'pin' => bcrypt('123456'), // Required format
            'level' => 'admin',
            'isDefault' => true
        ]);
    }
}
