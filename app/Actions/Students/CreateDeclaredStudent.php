<?php

namespace App\Actions\Students;

use App\Mail\StudentRegisteredMail;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentMails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateDeclaredStudent
{
    /** @param array<string,mixed> $data */
    public function handle(User $actor, array $data): Student
    {
        return Cache::lock('students:manual-identity-write', 60)->block(10, fn (): Student => DB::transaction(function () use ($actor, $data): Student {
            $recordFields = ['academic_year_id', 'establishment_id', 'level_id', 'program_id', 'repeat_code', 'scholarship_requested_at', 'scholarship_request_reference'];
            $student = app(SaveStudentIdentity::class)->persist($actor, null, collect($data)->except($recordFields)->all());
            $record = app(SaveStudentRecord::class)->initial($actor, $student, collect($data)->only($recordFields)->all());
            // Queued after the commit: a rolled back creation sends nothing.
            if (filled($student->email) && StudentMails::enabled('mail_student_registered')) {
                $record->loadMissing(['establishment:id,name', 'academicYear:id,name']);
                Mail::to($student->email)->queue(new StudentRegisteredMail(trim($student->last_name.' '.$student->first_names), $record->establishment->name, $record->academicYear->name));
            }

            return $student;
        }, 5));
    }
}
