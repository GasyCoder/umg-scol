<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCampaignStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\ScholarshipCampaign;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\ScholarshipAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReceiveScholarshipApplication
{
    /**
     * Records a paper file handed in at the Scolarité Centrale. Only the campaign and the student are
     * chosen by the agent; the annual declaration, actor and dates are derived and checked here.
     */
    public function handle(User $actor, int $campaignId, int $studentId, ?string $ddb, ?string $notes): ScholarshipApplication
    {
        Gate::forUser($actor)->authorize('receive', ScholarshipApplication::class);
        $ddb = ScholarshipApplication::normalizeDdb($ddb);
        try {
            return DB::transaction(function () use ($actor, $campaignId, $studentId, $ddb, $notes) {
                $campaign = ScholarshipCampaign::query()->lockForUpdate()->find($campaignId);
                if ($campaign === null || $campaign->status !== ScholarshipCampaignStatus::Open) {
                    $this->fail('campaign_id', 'La campagne n’est pas ouverte : aucune réception n’est possible.');
                }
                $student = Student::query()->lockForUpdate()->find($studentId);
                if ($student === null || ! $student->is_active) {
                    $this->fail('student_id', 'Étudiant introuvable ou fiche inactive.');
                }
                $existing = ScholarshipApplication::query()->where('scholarship_campaign_id', $campaign->id)->where('student_id', $student->id)->first();
                if ($existing !== null) {
                    $this->fail('student_id', 'Un dossier existe déjà pour cet étudiant dans cette campagne (n° '.$existing->application_number.').');
                }
                $record = $this->record($student, $campaign);
                $key = ScholarshipApplication::ddbKey($ddb);
                if ($key !== null && ScholarshipApplication::query()->where('scholarship_campaign_id', $campaign->id)->where('ddb_key', $key)->exists()) {
                    $this->fail('ddb_number', 'Ce numéro DDB est déjà utilisé dans cette campagne.');
                }
                $sequence = $campaign->last_application_sequence + 1;
                $campaign->forceFill(['last_application_sequence' => $sequence])->save();
                $application = ScholarshipApplication::query()->create([
                    'application_number' => config('scholarships.number_prefix').$campaign->id.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'scholarship_campaign_id' => $campaign->id, 'sequence' => $sequence, 'student_id' => $student->id, 'student_record_id' => $record->id,
                    'ddb_number' => $ddb, 'ddb_key' => $key, 'status' => ScholarshipApplicationStatus::Pending, 'received_by' => $actor->id, 'received_at' => now(),
                    'internal_notes' => filled($notes) ? $notes : null,
                ]);
                $application->checks()->createMany(array_map(fn (string $check) => ['key' => $check], array_keys((array) config('scholarships.checks'))));
                ScholarshipApplicationStatusHistory::query()->create(['scholarship_application_id' => $application->id, 'from_status' => null, 'to_status' => ScholarshipApplicationStatus::Pending, 'actor_id' => $actor->id]);
                ScholarshipAudit::application($actor, $application, 'application_received', ['student_record_id' => $record->id]);

                return $application;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            // A concurrent reception won the race (double click, two agents).
            $this->fail('student_id', 'Ce dossier vient d’être enregistré ou le numéro DDB est déjà utilisé. Actualisez la recherche.');
        }
    }

    /** The single active declaration of the campaign year, with consistent references; never guessed. */
    private function record(Student $student, ScholarshipCampaign $campaign): StudentRecord
    {
        $records = StudentRecord::query()->with(['establishment:id,is_active', 'level:id,is_active', 'program:id,establishment_id,is_active'])
            ->where('student_id', $student->id)->where('academic_year_id', $campaign->academic_year_id)->lockForUpdate()->get();
        $active = $records->filter(fn (StudentRecord $record) => $record->status->value === 'active');
        if ($records->isEmpty()) {
            $this->fail('student_id', 'Aucune situation de cet étudiant pour l’année universitaire de la campagne : l’établissement doit d’abord le déclarer.');
        }
        if ($active->count() !== 1) {
            $this->fail('student_id', $active->isEmpty() ? 'La situation de l’année de la campagne est inactive : contactez l’établissement.' : 'Plusieurs situations actives pour l’année : à régulariser avant réception.');
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
