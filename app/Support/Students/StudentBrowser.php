<?php

namespace App\Support\Students;

use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Two ways through the declared students, inside the actor's perimeter and for one academic year
 * (the current one by default, or all years in the list):
 * - cards, step by step: establishment → level → mention → program → list;
 * - the whole list at once ("Tous les étudiants", view=list), with every filter (also used by the export).
 * A search, a status or « all years » always shows the list. An establishment account starts at its own establishment.
 */
final class StudentBrowser
{
    public const NONE = 'none';

    public const ALL_YEARS = 'all';

    /** Null when every year is listed (requested, or no current year defined). */
    public readonly ?AcademicYear $year;

    public readonly ?int $establishmentId;

    public readonly ?int $levelId;

    public readonly ?string $mention;

    public readonly ?string $program;

    public readonly ?string $status;

    public readonly string $search;

    /** The whole list was asked for, instead of the cards. */
    public readonly bool $listView;

    /** @param array<string,mixed> $filters validated StudentListRequest data */
    public function __construct(private readonly User $user, array $filters)
    {
        $this->listView = ($filters['view'] ?? null) === 'list';
        $requestedYear = $filters['academic_year_id'] ?? null;
        $this->year = match (true) {
            $requestedYear === self::ALL_YEARS => null,
            $requestedYear !== null => AcademicYear::query()->find((int) $requestedYear, ['id', 'name', 'is_current']),
            default => AcademicYear::query()->where('is_current', true)->where('is_active', true)->first(['id', 'name', 'is_current']),
        };
        $this->establishmentId = $user->hasRole('establishment') ? (int) $user->establishment_id : (isset($filters['establishment_id']) ? (int) $filters['establishment_id'] : null);
        $this->levelId = isset($filters['level_id']) ? (int) $filters['level_id'] : null;
        $this->mention = isset($filters['mention_id']) ? (string) $filters['mention_id'] : null;
        $this->program = isset($filters['program_id']) ? (string) $filters['program_id'] : null;
        $this->status = isset($filters['status']) ? (string) $filters['status'] : null;
        $this->search = trim((string) ($filters['search'] ?? ''));
    }

    /** Which screen to show: a card step, or the list. */
    public function step(): string
    {
        return match (true) {
            $this->listView || $this->search !== '' || $this->status !== null || $this->year === null => 'list',
            $this->establishmentId === null => 'establishments',
            $this->levelId === null => 'levels',
            $this->program !== null => 'list',
            $this->mention === null => 'mentions',
            default => 'programs',
        };
    }

    /** @return Builder<StudentRecord> */
    private function records(): Builder
    {
        return StudentRecord::query()->visibleTo($this->user)->where('student_records.academic_year_id', $this->year?->id)
            ->when($this->establishmentId !== null, fn ($q) => $q->where('student_records.establishment_id', $this->establishmentId))
            ->when($this->levelId !== null, fn ($q) => $q->where('student_records.level_id', $this->levelId));
    }

    /**
     * Cards of the current step with active/inactive situation counts.
     *
     * @return array<int,array{id:int|string,label:string,code:string|null,active:int,inactive:int}>
     */
    public function cards(): array
    {
        if ($this->year === null) {
            return [];
        }
        $counts = "sum(case when student_records.status = 'active' then 1 else 0 end) as active, sum(case when student_records.status = 'active' then 0 else 1 end) as inactive";
        $query = match ($this->step()) {
            'establishments' => $this->records()->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')->whereNull('establishments.deleted_at')
                ->groupBy('establishments.id', 'establishments.name', 'establishments.code')->orderBy('establishments.name')
                ->selectRaw("establishments.id as id, establishments.name as label, establishments.code as code, {$counts}"),
            'levels' => $this->records()->join('levels', 'levels.id', '=', 'student_records.level_id')->whereNull('levels.deleted_at')
                ->groupBy('levels.id', 'levels.name', 'levels.code', 'levels.sort_order')->orderBy('levels.sort_order')->orderBy('levels.name')
                ->selectRaw("levels.id as id, levels.name as label, levels.code as code, {$counts}"),
            'mentions' => $this->withPrograms()->leftJoin('mentions', 'mentions.id', '=', 'programs.mention_id')->whereNull('mentions.deleted_at')
                ->groupBy('mentions.id', 'mentions.name', 'mentions.code')->orderByRaw('mentions.id is null')->orderBy('mentions.name')
                ->selectRaw("mentions.id as id, mentions.name as label, mentions.code as code, {$counts}"),
            'programs' => $this->withPrograms()->tap(fn ($q) => $this->mention === self::NONE ? $q->whereNull('programs.mention_id') : $q->where('programs.mention_id', (int) $this->mention))
                ->groupBy('programs.id', 'programs.name', 'programs.code')->orderByRaw('programs.id is null')->orderBy('programs.name')
                ->selectRaw("programs.id as id, programs.name as label, programs.code as code, {$counts}"),
            default => null,
        };
        if ($query === null) {
            return [];
        }
        $none = $this->step() === 'mentions' ? 'Sans mention' : 'Sans parcours';

        return $query->toBase()->get()->map(fn ($row) => [
            'id' => $row->id === null ? self::NONE : (int) $row->id, 'label' => (string) ($row->label ?? $none), 'code' => $row->code,
            'active' => (int) $row->active, 'inactive' => (int) $row->inactive,
        ])->values()->all();
    }

    /** @return Builder<StudentRecord> */
    private function withPrograms(): Builder
    {
        return $this->records()->leftJoin('programs', 'programs.id', '=', 'student_records.program_id')->whereNull('programs.deleted_at');
    }

    /**
     * Breadcrumb of the card steps; each entry carries the query parameters of its step.
     *
     * @return array<int,array{label:string,params:array<string,int|string>}>
     */
    public function trail(): array
    {
        $params = $this->year !== null ? ['academic_year_id' => $this->year->id] : [];
        $trail = [];
        if (! $this->user->hasRole('establishment')) {
            $trail[] = ['label' => 'Établissements', 'params' => $params];
        }
        if ($this->establishmentId !== null) {
            $params['establishment_id'] = $this->establishmentId;
            $trail[] = ['label' => (string) Establishment::query()->whereKey($this->establishmentId)->value('name'), 'params' => $params];
        }
        if ($this->levelId !== null) {
            $params['level_id'] = $this->levelId;
            $trail[] = ['label' => (string) Level::query()->whereKey($this->levelId)->value('name'), 'params' => $params];
        }
        if ($this->mention !== null) {
            $params['mention_id'] = $this->mention;
            $trail[] = ['label' => $this->mention === self::NONE ? 'Sans mention' : (string) Mention::query()->whereKey((int) $this->mention)->value('name'), 'params' => $params];
        }
        if ($this->program !== null) {
            $params['program_id'] = $this->program;
            $trail[] = ['label' => $this->program === self::NONE ? 'Sans parcours' : (string) Program::query()->whereKey((int) $this->program)->value('name'), 'params' => $params];
        }

        return $trail;
    }

    /**
     * Applies every filter of the list to a StudentRecord query (already limited by visibleTo).
     *
     * @param  Builder<StudentRecord>  $query
     * @return Builder<StudentRecord>
     */
    public function applyToList(Builder $query, bool $withStatus = true): Builder
    {
        // Qualified: the list and the export join the students table for their order.
        return $query->when($this->year !== null, fn ($q) => $q->where('student_records.academic_year_id', $this->year?->id))
            ->when($this->establishmentId !== null, fn ($q) => $q->where('student_records.establishment_id', $this->establishmentId))
            ->when($this->levelId !== null, fn ($q) => $q->where('student_records.level_id', $this->levelId))
            ->when($this->mention === self::NONE, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('student_records.program_id')->orWhereHas('program', fn ($p) => $p->whereNull('mention_id'))))
            ->when($this->mention !== null && $this->mention !== self::NONE, fn ($q) => $q->whereHas('program', fn ($p) => $p->where('mention_id', (int) $this->mention)))
            ->when($this->program === self::NONE, fn ($q) => $q->whereNull('student_records.program_id'))
            ->when($this->program !== null && $this->program !== self::NONE, fn ($q) => $q->where('student_records.program_id', (int) $this->program))
            ->when($withStatus && $this->status !== null, fn ($q) => $q->where('student_records.status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->whereHas('student', fn ($student) => $student->matching($this->search)));
    }
}
