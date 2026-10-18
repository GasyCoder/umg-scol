<?php

namespace App\Support\Scholarships;

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Work of the signed-in agent at the reception counter: counters and latest actions,
 * read from the applications they received and their own audit trail.
 */
class ReceptionActivity
{
    private const EVENTS = [
        'application_received' => 'Dossier reçu',
        'review_started' => 'Vérification commencée',
        'application_validated' => 'Dossier validé',
        'application_needs_correction' => 'Correction demandée',
        'application_rejected' => 'Dossier rejeté',
        'updated' => 'Fiche complétée',
    ];

    /** @return array<string,mixed> */
    public static function for(User $user, ?AcademicYear $year): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subDays(6);
        $received = ScholarshipApplication::query()->where('received_by', $user->id);
        $perDay = (clone $received)->where('received_at', '>=', $from)->get(['received_at'])
            ->countBy(fn (ScholarshipApplication $a) => $a->received_at->format('Y-m-d'));

        return [
            'today' => (int) ($perDay[$today->format('Y-m-d')] ?? 0),
            'week' => (int) $perDay->sum(),
            // Files of the academic year being received.
            'year' => $year !== null ? (clone $received)->where('academic_year_id', $year->id)->count() : null,
            'updatedToday' => self::mine($user)->where('log_name', 'students')->where('event', 'updated')->where('created_at', '>=', $today)->count(),
            'days' => collect(range(6, 0))->map(fn (int $ago) => $today->subDays($ago))
                ->map(fn (CarbonImmutable $day) => ['date' => $day->format('Y-m-d'), 'count' => (int) ($perDay[$day->format('Y-m-d')] ?? 0)])->all(),
            'recent' => self::recent($user),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function recent(User $user): array
    {
        return self::mine($user)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('log_name', 'scholarships')->whereIn('event', array_keys(self::EVENTS)))
                ->orWhere(fn ($q) => $q->where('log_name', 'students')->where('event', 'updated')))
            ->latest('id')->limit(8)->get()
            ->loadMorph('subject', [ScholarshipApplication::class => ['student:id,last_name,first_names']])
            ->map(function (Activity $activity) {
                $subject = $activity->subject;
                $student = $subject instanceof ScholarshipApplication ? $subject->student : ($subject instanceof Student ? $subject : null);

                return [
                    'id' => $activity->id, 'event' => $activity->event, 'label' => self::EVENTS[$activity->event] ?? $activity->event,
                    'student' => $student !== null ? trim($student->last_name.' '.$student->first_names) : null,
                    'reference' => $subject instanceof ScholarshipApplication ? $subject->reference() : null,
                    'href' => $subject instanceof ScholarshipApplication ? '/scholarships/applications/'.$subject->uuid : ($subject instanceof Student ? '/students/'.$subject->uuid : null),
                    // Names of the completed fields only, never their values.
                    'fields' => $activity->event === 'updated' ? array_values(array_diff((array) $activity->properties->get('changed_fields', []), ['is_active'])) : [],
                    'at' => $activity->created_at?->toIso8601String(),
                ];
            })->values()->all();
    }

    /** @return Builder<Activity> */
    private static function mine(User $user): Builder
    {
        return Activity::query()->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id);
    }
}
