<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ScholarshipCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScholarshipCampaign> */
class ScholarshipCampaignFactory extends Factory
{
    public function definition(): array
    {
        return ['academic_year_id' => AcademicYear::factory(), 'name' => 'Bourse nationale '.fake()->unique()->numberBetween(1000, 999999), 'status' => 'open', 'created_by' => User::factory()];
    }
}
