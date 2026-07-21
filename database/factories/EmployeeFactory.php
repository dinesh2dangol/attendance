<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->unique()->bothify('user_##??'),
            'employee_name' => $this->faker->name(),
            'join_date_eng' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'join_date_npt' => $this->faker->date('Y-m-d'),
            'photo' => 'https://picsum.photos/seed/'.md5($this->faker->unique()->word()).'/120/120',
            'status' => $this->faker->numberBetween(0, 2),
            'salary' => $this->faker->randomFloat(2, 350.00, 12000.00),
            'working_hours' => $this->faker->randomFloat(2, 0, 80),
            'part_time' => $this->faker->boolean(30),
            'department_id' => $this->faker->numberBetween(1, 5),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
        ];
    }
}
