<?php

namespace App\Support\Reporting;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCampaignStatus;
use App\Enums\StudentCorrectionStatus;
use App\Enums\StudentImportStatus;
use App\Enums\StudentLinkStatus;
use App\Models\Establishment;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentLinkRequest;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentConfirmationContext;
use App\Support\StudentInformationFingerprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SQL aggregates shared by the dashboards and the reports. Every query is bounded by a ReportScope:
 * establishment accounts never count or reveal another establishment's rows.
 * Callers check the permissions; these methods only aggregate.
 */
final class InstitutionalMetrics
{
    public function __construct(private readonly ReportScope $scope) {}

    /** @return Builder<StudentRecord> */
    private function records(): Builder
    {
        return StudentRecord::query()->where('student_records.academic_year_id', $this->scope->year?->id)
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('student_records.establishment_id', $this->scope->establishmentId));
    }

    /** @return array{declarations:int,active_declarations:int,active_students:int} */
    public function students(): array
    {
        if ($this->scope->year === null) {
            return ['declarations' => 0, 'active_declarations' => 0, 'active_students' => 0];
        }
        $row = $this->records()->join('students', 'students.id', '=', 'student_records.student_id')
            ->selectRaw("count(*) as declarations, sum(case when student_records.status = 'active' then 1 else 0 end) as active_declarations, count(distinct case when student_records.status = 'active' and students.is_active = ? then student_records.student_id end) as active_students", [true])
            ->toBase()->first();

        return ['declarations' => (int) $row->declarations, 'active_declarations' => (int) $row->active_declarations, 'active_students' => (int) $row->active_students];
    }

    /** @return array<int,array{id:int,label:string,value:int}> */
    public function studentsByLevel(): array
    {
        return $this->grouped($this->activeRecords()->join('levels', 'levels.id', '=', 'student_records.level_id')
            ->groupBy('levels.id', 'levels.name', 'levels.sort_order')->orderBy('levels.sort_order')->orderBy('levels.name')
            ->selectRaw('levels.id as id, levels.name as label, count(*) as value'));
    }

    /** @return array<int,array{id:int|null,label:string,value:int}> */
    public function studentsByProgram(): array
    {
        return $this->grouped($this->activeRecords()->leftJoin('programs', 'programs.id', '=', 'student_records.program_id')
            ->groupBy('programs.id', 'programs.name')->orderByRaw('programs.id is null')->orderByDesc('value')->orderBy('programs.name')
            ->selectRaw('programs.id as id, programs.name as label, count(*) as value'), 'Sans parcours');
    }

    /** @return array<int,array{id:int,label:string,value:int}> */
    public function studentsByEstablishment(): array
    {
        return $this->grouped($this->activeRecords()->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')
            ->groupBy('establishments.id', 'establishments.name')->orderByDesc('value')->orderBy('establishments.name')
            ->selectRaw('establishments.id as id, establishments.name as label, count(*) as value'));
    }

    /** @return Builder<StudentRecord> */
    private function activeRecords(): Builder
    {
        return $this->records()->where('student_records.status', 'active');
    }

    /**
     * @param  Builder<StudentRecord>|Builder<ScholarshipApplication>  $query
     * @return array<int,array{id:int|null,label:string,value:int}>
     */
    private function grouped(Builder $query, string $nullLabel = '—'): array
    {
        if ($this->scope->year === null) {
            return [];
        }

        return $query->toBase()->get()->map(fn ($row) => ['id' => $row->id === null ? null : (int) $row->id, 'label' => (string) ($row->label ?? $nullLabel), 'value' => (int) $row->value])->values()->all();
    }

    /**
     * Imports of the selected year: counts and row totals.
     *
     * @return array<string,int>
     */
    public function imports(): array
    {
        $row = StudentImport::query()->where('academic_year_id', $this->scope->year?->id)
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('establishment_id', $this->scope->establishmentId))
            ->selectRaw('count(*) as imports, sum(case when status = ? then 1 else 0 end) as completed, sum(case when status = ? then 1 else 0 end) as failed, sum(case when rejected_rows > 0 or warning_rows > 0 then 1 else 0 end) as with_anomalies, coalesce(sum(total_rows), 0) as total_rows, coalesce(sum(valid_rows), 0) as valid_rows, coalesce(sum(warning_rows), 0) as warning_rows, coalesce(sum(rejected_rows), 0) as rejected_rows, coalesce(sum(duplicate_rows), 0) as duplicate_rows, coalesce(sum(declared_rows), 0) as declared_rows', [StudentImportStatus::Completed->value, StudentImportStatus::Failed->value])
            ->toBase()->first();

        return array_map(fn ($value) => (int) $value, (array) $row);
    }

    /** @return array<int,array{id:int,filename:string,establishment:string,status:string,total_rows:int,rejected_rows:int,declared_rows:int,created_at:string}> */
    public function recentImports(int $limit = 5): array
    {
        return StudentImport::query()->with('establishment:id,name')
            ->when(! $this->scope->isGlobal(), fn ($q) => $q->where('establishment_id', $this->scope->establishmentId))
            ->latest('id')->limit($limit)->get(['id', 'establishment_id', 'original_filename', 'status', 'total_rows', 'rejected_rows', 'declared_rows', 'created_at'])
            ->map(fn (StudentImport $import) => [
                'id' => $import->id, 'filename' => (string) $import->original_filename, 'establishment' => (string) $import->establishment?->name, 'status' => $import->status->value,
                'total_rows' => (int) $import->total_rows, 'rejected_rows' => (int) $import->rejected_rows, 'declared_rows' => (int) $import->declared_rows, 'created_at' => $import->created_at?->toIso8601String() ?? '',
            ])->values()->all();
    }

    /**
     * Correction requests by status within the actor's review perimeter (all periods).
     *
     * @return array<string,int>
     */
    public function corrections(): array
    {
        $counts = StudentCorrectionRequest::query()->visibleTo($this->scope->user)->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return collect(StudentCorrectionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
    }

    public function pendingLinkRequests(): int
    {
        return StudentLinkRequest::query()->visibleTo($this->scope->user)->where('status', StudentLinkStatus::Pending->value)->count();
    }

    /**
     * Student confirmations for the current year, with exactly the rule of the student portal: the latest
     * confirmation must target the current declaration and match its fingerprint. Students who never
     * confirmed are counted in SQL; the others are checked by batches of 500 (fingerprints cannot be compared in SQL).
     *
     * @return array{confirmed:int,stale:int,never_confirmed:int,total:int}|null
     */
    public function confirmations(): ?array
    {
        if (! $this->scope->isCurrentYear()) {
            return null;
        }
        $base = fn () => $this->activeRecords()->whereHas('student', fn ($q) => $q->where('is_active', true));
        $total = $base()->count();
        $confirmed = 0;
        $checked = 0;
        $base()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('student_information_confirmations')->whereColumn('student_information_confirmations.student_id', 'student_records.student_id'))
            ->with(['student', ...StudentConfirmationContext::recordRelations()])->select('student_records.*')
            ->chunkById(500, function ($records) use (&$confirmed, &$checked) {
                $ids = $records->pluck('student_id')->all();
                $latest = StudentInformationConfirmation::query()
                    ->joinSub(StudentInformationConfirmation::query()->selectRaw('student_id as latest_student_id, max(sequence) as latest_sequence')->whereIn('student_id', $ids)->groupBy('student_id'), 'latest', fn ($join) => $join
                        ->on('student_information_confirmations.student_id', '=', 'latest.latest_student_id')->on('student_information_confirmations.sequence', '=', 'latest.latest_sequence'))
                    ->get(['student_information_confirmations.student_id', 'student_information_confirmations.student_record_id', 'student_information_confirmations.information_fingerprint'])->keyBy('student_id');
                foreach ($records as $record) {
                    $checked++;
                    $last = $latest->get($record->student_id);
                    if ($last !== null && StudentConfirmationContext::matches($last, $record, StudentInformationFingerprint::compute($record->student, $record))) {
                        $confirmed++;
                    }
                }
            }, 'student_records.id', 'id');

        return ['confirmed' => $confirmed, 'stale' => $checked - $confirmed, 'never_confirmed' => $total - $checked, 'total' => $total];
    }

    /** @return Builder<ScholarshipApplication> */
    private function applications(): Builder
    {
        return ScholarshipApplication::query()->join('scholarship_campaigns', 'scholarship_campaigns.id', '=', 'scholarship_applications.scholarship_campaign_id')
            ->where('scholarship_campaigns.academic_year_id', $this->scope->year?->id);
    }

    /**
     * Paper files of the selected year's campaigns, by status, plus files received today.
     *
     * @return array{total:int,today:int,by_status:array<string,int>}
     */
    public function scholarships(): array
    {
        $rows = $this->applications()->toBase()->selectRaw('scholarship_applications.status as status, count(*) as aggregate, sum(case when scholarship_applications.received_at >= ? then 1 else 0 end) as today', [now()->startOfDay()])
            ->groupBy('scholarship_applications.status')->get()->keyBy('status');
        $byStatus = collect(ScholarshipApplicationStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) ($rows[$status->value]->aggregate ?? 0)])->all();

        return ['total' => array_sum($byStatus), 'today' => (int) $rows->sum('today'), 'by_status' => $byStatus];
    }

    /** @return array<int,array{id:int,label:string,value:int}> */
    public function scholarshipsByEstablishment(): array
    {
        return $this->grouped($this->applications()->join('student_records', 'student_records.id', '=', 'scholarship_applications.student_record_id')
            ->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')
            ->groupBy('establishments.id', 'establishments.name')->orderByDesc('value')->orderBy('establishments.name')
            ->selectRaw('establishments.id as id, establishments.name as label, count(*) as value'));
    }

    /** @return array{year:array<int,array{id:int,name:string,status:string}>,open:array<int,array{id:int,name:string}>} */
    public function campaigns(): array
    {
        $year = ScholarshipCampaign::query()->where('academic_year_id', $this->scope->year?->id)->orderBy('name')->get(['id', 'name', 'status']);

        return [
            'year' => $year->map(fn (ScholarshipCampaign $c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status->value])->values()->all(),
            'open' => ScholarshipCampaign::query()->where('status', ScholarshipCampaignStatus::Open->value)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
        ];
    }

    public function activeEstablishments(): int
    {
        return Establishment::query()->where('is_active', true)->count();
    }

    public function internalUsers(): int
    {
        return User::query()->role(['superadmin', 'developer', 'central_scholarship_office', 'establishment'])->count();
    }
}
