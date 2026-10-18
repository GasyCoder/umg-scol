<?php

namespace App\Support;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentInformationRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Bell of the student portal (ADR-089), derived from what already exists: the student's correction
 * requests (sent, saved at once, accepted, refused, cancelled), confirmations of the record, the
 * scholarship office's emails and the steps of the paper scholarship file. Only the moment the student
 * last opened the bell is stored, to count the unread ones. Never a value of the record.
 */
final class StudentPortalNotifications
{
    private const LIMIT = 30;

    /** @return array{items:list<array<string,mixed>>,unread:int} */
    public static function for(User $user): array
    {
        $seen = $user->portal_notifications_seen_at;
        $items = self::events($user)->sortByDesc(fn (array $item) => $item['at']->getTimestamp())->take(self::LIMIT)->values()
            ->map(fn (array $item) => [...$item, 'unread' => $seen === null || $item['at']->greaterThan($seen), 'at' => $item['at']->toIso8601String()]);

        return ['items' => array_values($items->all()), 'unread' => $items->where('unread', true)->count()];
    }

    public static function markSeen(User $user): void
    {
        $user->forceFill(['portal_notifications_seen_at' => now()])->save();
    }

    /** @return Collection<int,array{id:string,kind:string,title:string,body:string,at:CarbonInterface}> */
    private static function events(User $user): Collection
    {
        $studentId = (int) $user->student_id;

        return collect([...self::corrections($user, $studentId), ...self::messages($studentId), ...self::confirmations($studentId), ...self::applications($studentId)]);
    }

    /** @return list<array{id:string,kind:string,title:string,body:string,at:CarbonInterface}> */
    private static function corrections(User $user, int $studentId): array
    {
        $events = [];
        $corrections = StudentCorrectionRequest::query()->where('user_id', $user->id)->where('student_id', $studentId)->latest('id')->limit(self::LIMIT)->get();
        foreach ($corrections as $correction) {
            $label = $correction->field->label();
            $id = 'c-'.$correction->uuid;
            if ($correction->status === StudentCorrectionStatus::Approved && $correction->reviewed_by === null) {
                $events[] = ['id' => $id.'-applied', 'kind' => 'applied', 'title' => $label.' mis à jour', 'body' => 'Votre modification est enregistrée sur votre fiche.', 'at' => $correction->created_at];

                continue;
            }
            $events[] = ['id' => $id.'-sent', 'kind' => 'requested', 'title' => 'Demande envoyée', 'body' => $label.' : en attente de validation par la scolarité.', 'at' => $correction->created_at];
            $decided = $correction->reviewed_at;
            if ($correction->status === StudentCorrectionStatus::Approved && $decided !== null) {
                $events[] = ['id' => $id.'-approved', 'kind' => 'approved', 'title' => 'Correction acceptée', 'body' => $label.' : la scolarité a validé votre demande.', 'at' => $decided];
            } elseif ($correction->status === StudentCorrectionStatus::Rejected && $decided !== null) {
                $events[] = ['id' => $id.'-rejected', 'kind' => 'rejected', 'title' => 'Correction refusée', 'body' => $label.' : '.($correction->rejection_reason ?? 'demande refusée.'), 'at' => $decided];
            } elseif ($correction->status === StudentCorrectionStatus::Cancelled) {
                $events[] = ['id' => $id.'-cancelled', 'kind' => 'cancelled', 'title' => 'Demande annulée', 'body' => $label.'.', 'at' => $correction->updated_at ?? $correction->created_at];
            }
        }

        return $events;
    }

    /** @return list<array{id:string,kind:string,title:string,body:string,at:CarbonInterface}> */
    private static function messages(int $studentId): array
    {
        return array_values(StudentInformationRequest::query()->where('student_id', $studentId)->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (StudentInformationRequest $request): array => [
                'id' => 'm-'.$request->id, 'kind' => 'message', 'title' => 'Message de la scolarité',
                'body' => $request->fields === []
                    ? ($request->message ?? 'Vérifiez votre fiche étudiante.')
                    : 'À compléter ou vérifier : '.implode(', ', array_map(fn (string $field) => StudentCorrectionField::from($field)->label(), $request->fields)).'.',
                'at' => $request->created_at,
            ])->all());
    }

    /** @return list<array{id:string,kind:string,title:string,body:string,at:CarbonInterface}> */
    private static function confirmations(int $studentId): array
    {
        return array_values(StudentInformationConfirmation::query()->where('student_id', $studentId)->with('academicYear:id,name')->latest('id')->limit(5)->get()
            ->map(fn (StudentInformationConfirmation $confirmation): array => [
                'id' => 'v-'.$confirmation->id, 'kind' => 'confirmed', 'title' => 'Informations confirmées',
                'body' => 'Vous avez confirmé votre fiche'.($confirmation->academicYear ? ' pour '.$confirmation->academicYear->name : '').'.',
                'at' => $confirmation->confirmed_at,
            ])->all());
    }

    /**
     * Steps of the paper scholarship file the student can act on: received, correction asked (with the
     * reason, which the office writes for the student), validated, rejected. Internal notes never.
     *
     * @return list<array{id:string,kind:string,title:string,body:string,at:CarbonInterface}>
     */
    private static function applications(int $studentId): array
    {
        $applications = ScholarshipApplication::query()->where('student_id', $studentId)->with('academicYear:id,name')->latest('id')->limit(5)->get();
        $events = [];
        foreach ($applications as $application) {
            $events[] = ['id' => 'a-'.$application->id, 'kind' => 'application', 'title' => 'Dossier de bourse reçu', 'body' => 'N° DDB '.$application->reference().' · '.$application->academicYear->name.'.', 'at' => $application->received_at];
        }
        $history = ScholarshipApplicationStatusHistory::query()->whereIn('scholarship_application_id', $applications->pluck('id'))
            ->whereIn('to_status', [ScholarshipApplicationStatus::Validated->value, ScholarshipApplicationStatus::NeedsCorrection->value, ScholarshipApplicationStatus::Rejected->value])
            ->latest('id')->limit(self::LIMIT)->get();
        foreach ($history as $step) {
            [$kind, $title, $body] = match ($step->to_status) {
                ScholarshipApplicationStatus::Validated => ['approved', 'Dossier de bourse validé', 'Votre dossier papier est conforme.'],
                ScholarshipApplicationStatus::NeedsCorrection => ['rejected', 'Correction du dossier demandée', $step->reason ?? 'Contactez la scolarité.'],
                default => ['rejected', 'Dossier de bourse rejeté', 'Contactez la scolarité pour en connaître le motif.'],
            };
            $events[] = ['id' => 'h-'.$step->id, 'kind' => $kind, 'title' => $title, 'body' => $body, 'at' => $step->created_at];
        }

        return $events;
    }
}
