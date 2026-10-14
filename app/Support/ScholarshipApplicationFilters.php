<?php

namespace App\Support;

use App\Models\ScholarshipApplication;
use Illuminate\Database\Eloquent\Builder;

/** SQL filters shared by the file list and its CSV export, so both always match. */
class ScholarshipApplicationFilters
{
    public const KEYS = ['search', 'campaign_id', 'status', 'establishment_id', 'level_id', 'received_from', 'received_to'];

    /**
     * @param  Builder<ScholarshipApplication>  $query
     * @param  array<string,mixed>  $filters
     * @return Builder<ScholarshipApplication>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $query->when(filled($filters['campaign_id'] ?? null), fn ($q) => $q->where('scholarship_campaign_id', (int) $filters['campaign_id']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['establishment_id'] ?? null), fn ($q) => $q->whereHas('studentRecord', fn ($r) => $r->where('establishment_id', (int) $filters['establishment_id'])))
            ->when(filled($filters['level_id'] ?? null), fn ($q) => $q->whereHas('studentRecord', fn ($r) => $r->where('level_id', (int) $filters['level_id'])))
            ->when(filled($filters['received_from'] ?? null), fn ($q) => $q->where('received_at', '>=', $filters['received_from'].' 00:00:00'))
            ->when(filled($filters['received_to'] ?? null), fn ($q) => $q->where('received_at', '<=', $filters['received_to'].' 23:59:59'));
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $ddb = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) ScholarshipApplication::ddbKey($search));
            $query->where(function ($q) use ($escaped, $ddb, $search) {
                $q->whereRaw("application_number LIKE ? ESCAPE '!'", [mb_strtoupper($escaped).'%'])
                    ->orWhereRaw("ddb_key LIKE ? ESCAPE '!'", [$ddb.'%'])
                    ->orWhereHas('student', function ($s) use ($search) {
                        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                        $s->whereRaw("student_number LIKE ? ESCAPE '!'", [$term.'%'])->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%'.$term.'%'])->orWhereRaw("first_names LIKE ? ESCAPE '!'", ['%'.$term.'%']);
                    });
            });
        }

        return $query;
    }
}
