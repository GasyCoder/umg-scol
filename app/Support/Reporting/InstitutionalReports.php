<?php

namespace App\Support\Reporting;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\StudentCorrectionStatus;
use App\Models\ScholarshipApplication;
use App\Models\StudentCorrectionRequest;
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
            // Own activity: the situations this account declared (entered or imported).
            ->when($this->scope->isOwn(), fn ($q) => $q->where('student_records.declared_by', $this->scope->actorId))
            ->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')->whereNull('establishments.deleted_at')
            ->join('levels', 'levels.id', '=', 'student_records.level_id')->whereNull('levels.deleted_at')
            ->leftJoin('programs', 'programs.id', '=', 'student_records.program_id')->whereNull('programs.deleted_at')
            ->groupBy('establishments.id', 'establishments.name', 'levels.id', 'levels.name', 'levels.sort_order', 'programs.id', 'programs.name')
            ->orderBy('establishments.name')->orderBy('levels.sort_order')->orderBy('levels.name')->orderBy('programs.name')
            ->selectRaw("establishments.name as establishment, levels.name as level, programs.name as program, sum(case when student_records.status = 'active' then 1 else 0 end) as active, sum(case when student_records.status = 'inactive' then 1 else 0 end) as inactive, sum(case when student_records.status = 'pending' then 1 else 0 end) as pending, count(*) as total")
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
        $own = $this->scope->isOwn();

        return StudentImport::query()->where('student_imports.academic_year_id', $this->scope->year?->id)
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('student_imports.establishment_id', $this->scope->establishmentId))
            // Own activity: the files this account imported, the national ones (several establishments) included.
            ->when($own, fn ($q) => $q->where('student_imports.user_id', $this->scope->actorId)->leftJoin('establishments', fn ($join) => $join->on('establishments.id', '=', 'student_imports.establishment_id')->whereNull('establishments.deleted_at')))
            ->when(! $own, fn ($q) => $q->join('establishments', 'establishments.id', '=', 'student_imports.establishment_id')->whereNull('establishments.deleted_at'))
            ->groupBy('establishments.id', 'establishments.name')->orderBy('establishments.name')
            ->selectRaw("coalesce(establishments.name, 'Plusieurs établissements') as establishment, count(*) as imports, sum(case when student_imports.status = 'completed' then 1 else 0 end) as completed, sum(case when student_imports.status = 'failed' then 1 else 0 end) as failed, coalesce(sum(student_imports.total_rows), 0) as total_rows, coalesce(sum(student_imports.valid_rows), 0) as valid_rows, coalesce(sum(student_imports.warning_rows), 0) as warning_rows, coalesce(sum(student_imports.rejected_rows), 0) as rejected_rows, coalesce(sum(student_imports.duplicate_rows), 0) as duplicate_rows, coalesce(sum(student_imports.declared_rows), 0) as declared_rows")
            ->toBase()->get()->map(fn ($row) => ['establishment' => (string) $row->establishment, ...collect((array) $row)->except('establishment')->map(fn ($v) => (int) $v)->all()])->values()->all();
    }

    /** @return array<string,int> */
    public function corrections(): array
    {
        if (! $this->scope->isOwn()) {
            return $this->metrics->corrections();
        }
        // Own activity: the requests this account reviewed.
        $counts = StudentCorrectionRequest::query()->visibleTo($this->scope->user)->where('reviewed_by', $this->scope->actorId)
            ->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return collect(StudentCorrectionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
    }

    /**
     * Paper files of the academic year by establishment and level, one column per status.
     *
     * @return array<int,array<string,int|string>>
     */
    public function scholarships(): array
    {
        $statuses = ScholarshipApplicationStatus::cases();
        $pivot = implode(', ', array_map(fn ($status) => "sum(case when scholarship_applications.status = '{$status->value}' then 1 else 0 end) as {$status->value}", $statuses));

        return ScholarshipApplication::query()
            ->join('student_records', 'student_records.id', '=', 'scholarship_applications.student_record_id')->whereNull('student_records.deleted_at')
            ->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')->whereNull('establishments.deleted_at')
            ->join('levels', 'levels.id', '=', 'student_records.level_id')->whereNull('levels.deleted_at')
            ->where('scholarship_applications.academic_year_id', $this->scope->year?->id)
            // Own activity: the paper files this account received.
            ->when($this->scope->isOwn(), fn ($q) => $q->where('scholarship_applications.received_by', $this->scope->actorId))
            ->groupBy('establishments.id', 'establishments.name', 'levels.id', 'levels.name', 'levels.sort_order')
            ->orderBy('establishments.name')->orderBy('levels.sort_order')
            ->selectRaw("establishments.name as establishment, levels.name as level, {$pivot}, count(*) as total")
            ->toBase()->get()->map(fn ($row) => ['establishment' => (string) $row->establishment, 'level' => (string) $row->level, ...collect((array) $row)->except(['establishment', 'level'])->map(fn ($v) => (int) $v)->all()])->values()->all();
    }
}
