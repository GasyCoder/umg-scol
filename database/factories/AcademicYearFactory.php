<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('YEAR-####??'), 'starts_at' => null, 'ends_at' => null, 'is_current' => false, 'is_active' => true];
    }
}
