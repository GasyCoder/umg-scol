<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\Mention;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mention> */
class MentionFactory extends Factory
{
    public function definition(): array
    {
        return ['establishment_id' => Establishment::factory(), 'code' => strtoupper(fake()->unique()->bothify('MN-####??')), 'name' => fake()->words(2, true), 'is_active' => true];
    }
}
