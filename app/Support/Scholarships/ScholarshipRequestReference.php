<?php

namespace App\Support\Scholarships;

use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\StudentRecord;

/**
 * Reference of a scholarship request, given by the platform: « CODE-YEAR-00001 », one sequence per
 * establishment and academic year. The caller holds a lock on the establishment row (one writer at a time).
 */
final class ScholarshipRequestReference
{
    public static function next(int $establishmentId, int $academicYearId): string
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) Establishment::query()->whereKey($establishmentId)->value('code'))) ?: 'ETAB';
        $academicYear = AcademicYear::query()->find($academicYearId, ['id', 'name', 'starts_at']);
        // First year of « 2026-2027 », else the start date, else the current year.
        $year = preg_match('/\d{4}/', (string) $academicYear?->name, $match) === 1 ? $match[0] : (string) ($academicYear?->starts_at?->format('Y') ?? now()->year);
        $prefix = $code.'-'.$year.'-';
        $last = StudentRecord::query()->where('establishment_id', $establishmentId)->where('academic_year_id', $academicYearId)
            ->where('scholarship_request_reference', 'like', $prefix.'%')->pluck('scholarship_request_reference')
            ->map(fn ($reference) => (int) substr((string) $reference, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
