<?php

namespace App\Support\Reporting;

use App\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\StudentImport;
use App\Models\StudentRecord;

/**
 * V1 aggregated reports: grouped counts only, never one line per student, always inside the ReportScope.
 * The same rows feed the page and the CSV export, so both show exactly the same figures.
 */
final class InstitutionalReports
{
    private InstitutionalMetrics $metrics;

    public function __construct(private readonly ReportScope $scope)
    {
        $this->metrics = new InstitutionalMetrics($scope);
    }

    /**
     * Declarations of the year by establishment, level and program, split active/inactive.
     *
     * @return array<int,array{establishment:string,level:string,program:string,active:int,inactive:int,total:int}>
     */
    public function students(): array
    {
        if ($this->scope->year === null) {
            return [];
        }

        return StudentRecord::query()->where('student_records.academic_year_id', $this->scope->year->id)
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('student_records.establishment_id', $this->scope->establishmentId))
            ->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')->whereNull('establishments.deleted_at')
            ->join('levels', 'levels.id', '=', 'student_records.level_id')->whereNull('levels.deleted_at')
            ->leftJoin('programs', 'programs.id', '=', 'student_records.program_id')->whereNull('programs.deleted_at')
            ->groupBy('establishments.id', 'establishments.name', 'levels.id', 'levels.name', 'levels.sort_order', 'programs.id', 'programs.name')
            ->orderBy('establishments.name')->orderBy('levels.sort_order')->orderBy('levels.name')->orderBy('programs.name')
            ->selectRaw("establishments.name as establishment, levels.name as level, programs.name as program, sum(case when student_records.status = 'active' then 1 else 0 end) as active, sum(case when student_records.status = 'active' then 0 else 1 end) as inactive, count(*) as total")
            ->toBase()->get()->map(fn ($row) => [
                'establishment' => (string) $row->establishment, 'level' => (string) $row->level, 'program' => (string) ($row->program ?? 'Sans parcours'),
                'active' => (int) $row->active, 'inactive' => (int) $row->inactive, 'total' => (int) $row->total,
            ])->values()->all();
    }

    /**
     * Imports of the year by establishment.
     *
     * @return array<int,array<string,int|string>>
     */
    public function imports(): array
    {
        return StudentImport::query()->where('student_imports.academic_year_id', $this->scope->year?->id)
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('student_imports.establishment_id', $this->scope->establishmentId))
            ->join('establishments', 'establishments.id', '=', 'student_imports.establishment_id')->whereNull('establishments.deleted_at')
            ->groupBy('establishments.id', 'establishments.name')->orderBy('establishments.name')
            ->selectRaw("establishments.name as establishment, count(*) as imports, sum(case when student_imports.status = 'completed' then 1 else 0 end) as completed, sum(case when student_imports.status = 'failed' then 1 else 0 end) as failed, coalesce(sum(student_imports.total_rows), 0) as total_rows, coalesce(sum(student_imports.valid_rows), 0) as valid_rows, coalesce(sum(student_imports.warning_rows), 0) as warning_rows, coalesce(sum(student_imports.rejected_rows), 0) as rejected_rows, coalesce(sum(student_imports.duplicate_rows), 0) as duplicate_rows, coalesce(sum(student_imports.declared_rows), 0) as declared_rows")
            ->toBase()->get()->map(fn ($row) => ['establishment' => (string) $row->establishment, ...collect((array) $row)->except('establishment')->map(fn ($v) => (int) $v)->all()])->values()->all();
    }

    /** @return array<string,int> */
    public function corrections(): array
    {
        return $this->metrics->corrections();
    }

    /**
     * Paper files of the year's campaigns by campaign, establishment and level, one column per status.
     *
     * @return array<int,array<string,int|string>>
     */
    public function scholarships(): array
    {
        $statuses = ScholarshipApplicationStatus::cases();
        $pivot = implode(', ', array_map(fn ($status) => "sum(case when scholarship_applications.status = '{$status->value}' then 1 else 0 end) as {$status->value}", $statuses));

        return ScholarshipApplication::query()
            ->join('scholarship_campaigns', 'scholarship_campaigns.id', '=', 'scholarship_applications.scholarship_campaign_id')->whereNull('scholarship_campaigns.deleted_at')
            ->join('student_records', 'student_records.id', '=', 'scholarship_applications.student_record_id')->whereNull('student_records.deleted_at')
            ->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')->whereNull('establishments.deleted_at')
            ->join('levels', 'levels.id', '=', 'student_records.level_id')->whereNull('levels.deleted_at')
            ->where('scholarship_campaigns.academic_year_id', $this->scope->year?->id)
            ->groupBy('scholarship_campaigns.id', 'scholarship_campaigns.name', 'establishments.id', 'establishments.name', 'levels.id', 'levels.name', 'levels.sort_order')
            ->orderBy('scholarship_campaigns.name')->orderBy('establishments.name')->orderBy('levels.sort_order')
            ->selectRaw("scholarship_campaigns.name as campaign, establishments.name as establishment, levels.name as level, {$pivot}, count(*) as total")
            ->toBase()->get()->map(fn ($row) => ['campaign' => (string) $row->campaign, 'establishment' => (string) $row->establishment, 'level' => (string) $row->level, ...collect((array) $row)->except(['campaign', 'establishment', 'level'])->map(fn ($v) => (int) $v)->all()])->values()->all();
    }
}
