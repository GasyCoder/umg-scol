<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return ['student_number' => fake()->unique()->bothify('TEST-####????'), 'last_name' => fake()->lastName(), 'first_names' => fake()->firstName(), 'birth_date' => fake()->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'), 'email' => fake()->safeEmail(), 'is_active' => true];
    }
}
