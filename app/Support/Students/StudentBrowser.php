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
 * Drill-down of declared students: establishment → level → mention → program → list.
 * Every step counts the declarations of the selected academic year (current year by default)
 * inside the actor's perimeter; an establishment account always starts from its own establishment.
 */
final class StudentBrowser
{
    public const NONE = 'none';

    public readonly ?AcademicYear $year;

    public readonly ?int $establishmentId;

    public readonly ?int $levelId;

    public readonly ?string $mention;

    public readonly ?string $program;

    /** @param array<string,mixed> $filters validated StudentListRequest data */
    public function __construct(private readonly User $user, private readonly array $filters)
    {
        $this->year = isset($filters['academic_year_id'])
            ? AcademicYear::query()->find((int) $filters['academic_year_id'], ['id', 'name', 'is_current'])
            : AcademicYear::query()->where('is_current', true)->where('is_active', true)->first(['id', 'name', 'is_current']);
        $this->establishmentId = $user->hasRole('establishment') ? (int) $user->establishment_id : (isset($filters['establishment_id']) ? (int) $filters['establishment_id'] : null);
        $this->levelId = isset($filters['level_id']) ? (int) $filters['level_id'] : null;
        $this->mention = isset($filters['mention_id']) ? (string) $filters['mention_id'] : null;
        $this->program = isset($filters['program_id']) ? (string) $filters['program_id'] : null;
    }

    /** Which screen to show: a card step, or the paginated list. */
    public function step(): string
    {
        return match (true) {
            filled($this->filters['search'] ?? null) || ($this->filters['view'] ?? null) === 'list' => 'list',
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
     * Cards of the current step with active/inactive declaration counts.
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
            'establishments' => $this->records()->join('establishments', 'establishments.id', '=', 'student_records.establishment_id')
                ->groupBy('establishments.id', 'establishments.name', 'establishments.code')->orderBy('establishments.name')
                ->selectRaw("establishments.id as id, establishments.name as label, establishments.code as code, {$counts}"),
            'levels' => $this->records()->join('levels', 'levels.id', '=', 'student_records.level_id')
                ->groupBy('levels.id', 'levels.name', 'levels.code', 'levels.sort_order')->orderBy('levels.sort_order')->orderBy('levels.name')
                ->selectRaw("levels.id as id, levels.name as label, levels.code as code, {$counts}"),
            'mentions' => $this->withPrograms()->leftJoin('mentions', 'mentions.id', '=', 'programs.mention_id')
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
        return $this->records()->leftJoin('programs', 'programs.id', '=', 'student_records.program_id');
    }

    /**
     * Filters for the final list (Eloquent query of StudentController).
     *
     * @param  Builder<StudentRecord>  $query
     * @return Builder<StudentRecord>
     */
    public function applyToList(Builder $query): Builder
    {
        // The list is limited to a year only when one is requested (the screens always send it); a direct search spans all years.
        return $query->when($this->year !== null && isset($this->filters['academic_year_id']), fn ($q) => $q->where('academic_year_id', $this->year->id))
            ->when($this->establishmentId !== null, fn ($q) => $q->where('establishment_id', $this->establishmentId))
            ->when($this->levelId !== null, fn ($q) => $q->where('level_id', $this->levelId))
            ->when($this->mention === self::NONE, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('program_id')->orWhereHas('program', fn ($p) => $p->whereNull('mention_id'))))
            ->when($this->mention !== null && $this->mention !== self::NONE, fn ($q) => $q->whereHas('program', fn ($p) => $p->where('mention_id', (int) $this->mention)))
            ->when($this->program === self::NONE, fn ($q) => $q->whereNull('program_id'))
            ->when($this->program !== null && $this->program !== self::NONE, fn ($q) => $q->where('program_id', (int) $this->program));
    }

    /**
     * Breadcrumb of the drill-down; each entry carries the query parameters of its step.
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
            $trail[] = ['label' => $this->mention === self::NONE ? 'Sans mention' : (string) Mention::query()->whereKey((int) $this->mention)->where('establishment_id', $this->establishmentId)->value('name'), 'params' => $params];
        }
        if ($this->program !== null) {
            $params['program_id'] = $this->program;
            $trail[] = ['label' => $this->program === self::NONE ? 'Sans parcours' : (string) Program::query()->whereKey((int) $this->program)->where('establishment_id', $this->establishmentId)->value('name'), 'params' => $params];
        }

        return $trail;
    }
}
