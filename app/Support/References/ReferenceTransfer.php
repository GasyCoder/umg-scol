<?php

namespace App\Support\References;

use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Shared definition of the mention/program spreadsheets (model, export, import): columns, perimeter and list query.
 * The same columns are used everywhere so an export can be edited and imported again.
 */
final class ReferenceTransfer
{
    public const KINDS = ['mentions', 'programs'];

    /** Same rule as SaveMentionRequest / SaveProgramRequest, applied after trim + uppercase. */
    public const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9._-]*$/';

    public const CODE_MAX = 30;

    public const NAME_MAX = 255;

    public const MAX_ROWS = 1000;

    public const MAX_KILOBYTES = 2048;

    /** @return class-string<Mention>|class-string<Program> */
    public static function model(string $kind): string
    {
        return match ($kind) {
            'mentions' => Mention::class,
            'programs' => Program::class,
            default => throw new InvalidArgumentException('Unknown referential kind.'),
        };
    }

    public static function title(string $kind): string
    {
        return $kind === 'mentions' ? 'Mentions' : 'Parcours';
    }

    /**
     * Column keys and their French headers, in file order.
     *
     * @return array<string,string>
     */
    public static function columns(string $kind): array
    {
        return $kind === 'mentions'
            ? ['establishment' => 'Code établissement', 'code' => 'Code mention', 'name' => 'Nom de la mention']
            : ['establishment' => 'Code établissement', 'mention' => 'Code mention', 'code' => 'Code parcours', 'name' => 'Nom du parcours'];
    }

    /** @return Builder<Establishment> */
    public static function establishments(User $user): Builder
    {
        return Establishment::query()->when($user->hasRole('establishment'), fn (Builder $query) => $query->whereKey($user->establishment_id));
    }

    /**
     * The referential list as shown on the index page (perimeter + filters), shared with the export.
     *
     * @param  array{search?: string|null, establishment_id?: int|string|null}  $filters
     * @return Builder<Mention>|Builder<Program>
     */
    public static function listQuery(string $kind, User $user, array $filters): Builder
    {
        return $kind === 'mentions'
            ? self::filter(Mention::query()->with(['establishment:id,code,name', 'domain:id,code,name']), $user, $filters)
            : self::filter(Program::query()->with(['establishment:id,code,name', 'mention:id,code,name']), $user, $filters);
    }

    /**
     * @param  array{search?: string|null, establishment_id?: int|string|null}  $filters
     * @return list<list<string>>
     */
    public static function exportRows(string $kind, User $user, array $filters): array
    {
        if ($kind === 'mentions') {
            return array_values(self::filter(Mention::query()->with('establishment:id,code'), $user, $filters)->get()
                ->map(fn (Mention $record): array => [(string) $record->establishment?->code, (string) $record->code, $record->name])->all());
        }

        return array_values(self::filter(Program::query()->with(['establishment:id,code', 'mention:id,code']), $user, $filters)->get()
            ->map(fn (Program $record): array => [(string) $record->establishment?->code, (string) $record->mention?->code, (string) $record->code, $record->name])->all());
    }

    /**
     * @template TModel of Mention|Program
     *
     * @param  Builder<TModel>  $query
     * @param  array{search?: string|null, establishment_id?: int|string|null}  $filters
     * @return Builder<TModel>
     */
    private static function filter(Builder $query, User $user, array $filters): Builder
    {
        if ($user->hasRole('establishment')) {
            $query->where('establishment_id', $user->establishment_id);
        }
        if (filled($filters['establishment_id'] ?? null)) {
            $query->where('establishment_id', (int) $filters['establishment_id']);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        return $query->orderBy('name')->orderBy('id');
    }

    /**
     * Reference codes the actor may use in the file: establishments, and coded mentions for programs.
     *
     * @return array{headings: list<string>, rows: list<list<string>>}
     */
    public static function codes(string $kind, User $user): array
    {
        $establishments = self::establishments($user)->orderBy('code')->get(['id', 'code', 'name', 'is_active']);
        if ($kind === 'mentions') {
            return ['headings' => ['Code établissement', 'Établissement', 'Statut'], 'rows' => array_values($establishments
                ->map(fn (Establishment $e): array => [(string) $e->code, $e->name, $e->is_active ? 'Actif' : 'Inactif'])->all())];
        }
        $mentions = Mention::query()->whereIn('establishment_id', $establishments->modelKeys())->whereNotNull('code')
            ->orderBy('code')->get(['establishment_id', 'code', 'name'])->groupBy('establishment_id');
        $rows = [];
        foreach ($establishments as $establishment) {
            $own = $mentions->get($establishment->id, collect());
            if ($own->isEmpty()) {
                $rows[] = [(string) $establishment->code, $establishment->name, '', ''];
            }
            foreach ($own as $mention) {
                $rows[] = [(string) $establishment->code, $establishment->name, (string) $mention->code, $mention->name];
            }
        }

        return ['headings' => ['Code établissement', 'Établissement', 'Code mention', 'Nom de la mention'], 'rows' => $rows];
    }
}
