<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentRecord> */
class StudentRecordFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => Student::factory(), 'academic_year_id' => AcademicYear::factory(), 'establishment_id' => Establishment::factory(), 'level_id' => Level::factory(), 'program_id' => null, 'repeat_code' => 'N', 'status' => 'active', 'declared_by' => User::factory(), 'declared_at' => now()];
    }
}
