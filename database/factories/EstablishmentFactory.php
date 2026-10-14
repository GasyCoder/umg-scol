<?php

namespace Database\Factories;

use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Establishment> */
class EstablishmentFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('EST-####??')), 'name' => fake()->company(), 'is_active' => true];
    }
}
