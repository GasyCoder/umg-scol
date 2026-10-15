<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return ['last_name' => fake()->lastName(), 'first_names' => fake()->firstName(), 'sex' => fake()->randomElement(['M', 'F']), 'birth_date' => fake()->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'), 'cin' => fake()->unique()->numerify('4###########'), 'nationality' => 'Malagasy', 'email' => fake()->unique()->safeEmail(), 'is_active' => true];
    }
}
