<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Program> */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return ['establishment_id' => Establishment::factory(), 'code' => strtoupper(fake()->unique()->bothify('PR-####??')), 'name' => fake()->words(3, true), 'is_active' => true];
    }
}
