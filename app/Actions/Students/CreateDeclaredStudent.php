<?php

namespace App\Actions\Students;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateDeclaredStudent
{
    /** @param array<string,mixed> $data */
    public function handle(User $actor, array $data): Student
    {
        return Cache::lock('students:manual-identity-write', 60)->block(10, fn (): Student => DB::transaction(function () use ($actor, $data): Student {
            $recordFields = ['academic_year_id', 'establishment_id', 'level_id', 'program_id', 'repeat_code', 'scholarship_requested_at', 'scholarship_request_reference'];
            $student = app(SaveStudentIdentity::class)->persist($actor, null, collect($data)->except($recordFields)->all());
            app(SaveStudentRecord::class)->initial($actor, $student, collect($data)->only($recordFields)->all());

            return $student;
        }, 5));
    }
}
