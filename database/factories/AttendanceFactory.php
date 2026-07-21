<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $checkIn = $this->faker->time('H:i:s', '09:30:00');
        $checkout = $this->faker->time('H:i:s', '17:30:00');

        return [
            'attendance_date' => $this->faker->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['present', 'absent', 'remote']),
            'check_in' => $this->faker->boolean(80) ? $checkIn : null,
            'check_out' => $this->faker->boolean(80) ? $checkout : null,
            'notes' => $this->faker->optional()->sentence(6),
        ];
    }
}
