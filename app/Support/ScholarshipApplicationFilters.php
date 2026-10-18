<?php

namespace App\Support;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\SituationVerification;
use App\Models\ScholarshipApplication;
use Illuminate\Database\Eloquent\Builder;

/** SQL filters shared by the file list and its Excel export, so both always match. */
class ScholarshipApplicationFilters
{
    public const KEYS = ['search', 'academic_year_id', 'status', 'establishment_id', 'level_id', 'received_from', 'received_to'];

    /**
     * @param  Builder<ScholarshipApplication>  $query
     * @param  array<string,mixed>  $filters
     * @return Builder<ScholarshipApplication>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $query->when(filled($filters['academic_year_id'] ?? null), fn ($q) => $q->where($q->qualifyColumn('academic_year_id'), (int) $filters['academic_year_id']))
            ->when(filled($filters['status'] ?? null), fn ($q) => self::status($q, (string) $filters['status']))
            ->when(filled($filters['establishment_id'] ?? null), fn ($q) => $q->whereHas('studentRecord', fn ($r) => $r->where('establishment_id', (int) $filters['establishment_id'])))
            ->when(filled($filters['level_id'] ?? null), fn ($q) => $q->whereHas('studentRecord', fn ($r) => $r->where('level_id', (int) $filters['level_id'])))
            ->when(filled($filters['received_from'] ?? null), fn ($q) => $q->where($q->qualifyColumn('received_at'), '>=', $filters['received_from'].' 00:00:00'))
            ->when(filled($filters['received_to'] ?? null), fn ($q) => $q->where($q->qualifyColumn('received_at'), '<=', $filters['received_to'].' 23:59:59'));
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $ddb = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) ScholarshipApplication::ddbKey($search));
            $query->where(function ($q) use ($escaped, $ddb, $search) {
                $q->whereRaw("application_number LIKE ? ESCAPE '!'", [mb_strtoupper($escaped).'%'])
                    ->orWhereRaw("ddb_key LIKE ? ESCAPE '!'", [$ddb.'%'])
                    ->orWhereHas('student', function ($s) use ($search) {
                        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                        $s->whereRaw("cin LIKE ? ESCAPE '!'", [preg_replace('/[\s.]+/u', '', $term).'%'])->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%'.$term.'%'])->orWhereRaw("first_names LIKE ? ESCAPE '!'", ['%'.$term.'%']);
                    });
            });
        }

        return $query;
    }

    /**
     * Shown status: « Reçu » and « En attente de la liste » split the received files on the
     * verification of their situation, so that each file falls in exactly one tab.
     *
     * @param  Builder<ScholarshipApplication>  $query
     */
    private static function status(Builder $query, string $status): void
    {
        $verified = fn ($r) => $r->where('verification', SituationVerification::Verified->value);
        match ($status) {
            ScholarshipApplication::AWAITING_LIST => $query->where($query->qualifyColumn('status'), ScholarshipApplicationStatus::Pending->value)->whereDoesntHave('studentRecord', $verified),
            ScholarshipApplicationStatus::Pending->value => $query->where($query->qualifyColumn('status'), $status)->whereHas('studentRecord', $verified),
            default => $query->where($query->qualifyColumn('status'), $status),
        };
    }

    /**
     * Number of files per shown status, with the other filters.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,int>
     */
    public static function counts(array $filters): array
    {
        $rows = self::apply(ScholarshipApplication::query(), array_diff_key($filters, ['status' => true]))->toBase()
            ->join('student_records as situation', 'situation.id', '=', 'scholarship_applications.student_record_id')
            ->selectRaw('CASE WHEN scholarship_applications.status = ? AND situation.verification <> ? THEN ? ELSE scholarship_applications.status END as shown', [ScholarshipApplicationStatus::Pending->value, SituationVerification::Verified->value, ScholarshipApplication::AWAITING_LIST])
            ->selectRaw('COUNT(*) as aggregate')->groupBy('shown')->pluck('aggregate', 'shown');

        return collect([ScholarshipApplication::AWAITING_LIST, ...array_map(fn ($s) => $s->value, ScholarshipApplicationStatus::cases())])
            ->mapWithKeys(fn (string $status) => [$status => (int) ($rows[$status] ?? 0)])->all();
    }
}
