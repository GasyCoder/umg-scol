<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Mail\ScholarshipFileReceivedMail;
use App\Models\AcademicYear;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\ScholarshipAudit;
use App\Support\Scholarships\DdbNumber;
use App\Support\StudentMails;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReceiveScholarshipApplication
{
    /**
     * Records a paper file handed in at the Scolarité Centrale for the current academic year
     * (ADR-085). Only the student is chosen by the agent (the DDB number is generated unless given);
     * the year, annual declaration, actor and dates are derived and checked here.
     */
    public function handle(User $actor, int $studentId, ?string $ddb, ?string $notes): ScholarshipApplication
    {
        Gate::forUser($actor)->authorize('receive', ScholarshipApplication::class);
        $ddb = ScholarshipApplication::normalizeDdb($ddb);
        try {
            return DB::transaction(function () use ($actor, $studentId, $ddb, $notes) {
                // The year row is locked: it holds the reception sequence.
                $year = AcademicYear::query()->where('is_current', true)->where('is_active', true)->lockForUpdate()->first();
                if ($year === null) {
                    $this->fail('student_id', 'Aucune année universitaire courante : la réception des dossiers est fermée.');
                }
                $student = Student::query()->lockForUpdate()->find($studentId);
                if ($student === null || ! $student->is_active) {
                    $this->fail('student_id', 'Étudiant introuvable ou fiche inactive.');
                }
                // One scholarship request per student and per academic year (the student row is locked:
                // two counters cannot both register it).
                $existing = ScholarshipApplication::query()->ofStudentYear($student->id, $year->id)->first();
                if ($existing !== null) {
                    $this->fail('student_id', 'Une seule demande de bourse par année universitaire : cet étudiant a déjà un dossier en '.$year->name.' (N° DDB '.$existing->reference().').');
                }
                $record = $this->record($student, $year);
                $sequence = $year->last_application_sequence + 1;
                // Without a number written on the form, the DDB number is generated from the platform pattern.
                $ddb ??= DdbNumber::for($year, $record->establishment, $sequence);
                $key = ScholarshipApplication::ddbKey($ddb);
                if ($key !== null && ScholarshipApplication::withTrashed()->where('academic_year_id', $year->id)->where('ddb_key', $key)->exists()) {
                    $this->fail('ddb_number', 'Ce numéro DDB est déjà utilisé cette année universitaire.');
                }
                $year->forceFill(['last_application_sequence' => $sequence])->save();
                $application = ScholarshipApplication::query()->create([
                    // Technical number, never shown (ADR-081).
                    'application_number' => config('scholarships.number_prefix').'-'.$year->name.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'academic_year_id' => $year->id, 'sequence' => $sequence, 'student_id' => $student->id, 'student_record_id' => $record->id,
                    'ddb_number' => $ddb, 'ddb_key' => $key, 'status' => ScholarshipApplicationStatus::Pending, 'received_by' => $actor->id, 'received_at' => now(),
                    'internal_notes' => filled($notes) ? $notes : null,
                ]);
                $application->checks()->createMany(array_map(fn (string $check) => ['key' => $check], array_keys((array) config('scholarships.checks'))));
                ScholarshipApplicationStatusHistory::query()->create(['scholarship_application_id' => $application->id, 'from_status' => null, 'to_status' => ScholarshipApplicationStatus::Pending, 'actor_id' => $actor->id]);
                ScholarshipAudit::application($actor, $application, 'application_received', ['student_record_id' => $record->id]);
                // The student receives the DDB number by email, queued after the commit.
                if (filled($student->email) && StudentMails::enabled('mail_scholarship_received')) {
                    Mail::to($student->email)->queue(new ScholarshipFileReceivedMail(
                        trim($student->last_name.' '.$student->first_names), $application->reference(), $year->name, $application->received_at->format('d/m/Y'),
                    ));
                }

                return $application;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            // A concurrent reception won the race (double click, two agents).
            $this->fail('student_id', 'Ce dossier vient d’être enregistré ou le numéro DDB est déjà utilisé. Actualisez la recherche.');
        }
    }

    /** The single active declaration of the year, with consistent references; never guessed. */
    private function record(Student $student, AcademicYear $year): StudentRecord
    {
        $records = StudentRecord::query()->with(['establishment:id,code,is_active', 'level:id,is_active', 'program:id,establishment_id,is_active'])
            ->where('student_id', $student->id)->where('academic_year_id', $year->id)->lockForUpdate()->get();
        // A situation waiting for its validation can be received (not validated): it counts as the situation of the year.
        $active = $records->filter(fn (StudentRecord $record) => in_array($record->status->value, ['active', 'pending'], true));
        if ($records->isEmpty()) {
            $this->fail('student_id', 'Aucune situation de cet étudiant pour l’année universitaire '.$year->name.' : l’établissement doit d’abord le déclarer.');
        }
        if ($active->count() !== 1) {
            $this->fail('student_id', $active->isEmpty() ? 'La situation de l’année '.$year->name.' est inactive : contactez l’établissement.' : 'Plusieurs situations actives pour l’année : à régulariser avant réception.');
        }
        $record = $active->first();
        if (! $record->establishment->is_active || ! $record->level->is_active || ($record->program !== null && ($record->program->establishment_id !== $record->establishment_id || ! $record->program->is_active))) {
            $this->fail('student_id', 'La situation de l’année référence un établissement, un niveau ou un parcours inactif ou incohérent.');
        }

        return $record;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
