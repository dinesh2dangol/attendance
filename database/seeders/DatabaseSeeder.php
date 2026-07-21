<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);

        if (! \App\Models\Department::query()->exists()) {
            \App\Models\Department::insert([
                ['department_name' => 'Human Resources', 'created_at' => now(), 'updated_at' => now()],
                ['department_name' => 'Engineering', 'created_at' => now(), 'updated_at' => now()],
                ['department_name' => 'Sales', 'created_at' => now(), 'updated_at' => now()],
                ['department_name' => 'Marketing', 'created_at' => now(), 'updated_at' => now()],
                ['department_name' => 'Support', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (! \App\Models\Employee::query()->exists()) {
            \Database\Factories\EmployeeFactory::new()->count(10)->create();
        }
    }
}
