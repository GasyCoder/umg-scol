<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Support\StudentImportColumns as Columns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Levels, domains, establishments, mentions and programs of the UMG (database/seeders/data/umg_references.php),
 * so that the establishment lists and the national files find their references.
 * Re-runnable: only what is missing is created; nothing edited in Référentiels is overwritten, nothing deleted is restored.
 */
class UmgReferenceSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{domains: array<string,string>, establishments: array<string,array{name:string, aliases?: list<string>, mentions: array<string,array{domain:string|null, programs: list<string>}>}>} $data */
        $data = require database_path('seeders/data/umg_references.php');

        DB::transaction(function () use ($data) {
            foreach (Level::STANDARD as $code => [$name, $order]) {
                if (! Level::query()->withTrashed()->where('code', $code)->exists()) {
                    Level::query()->create(['code' => $code, 'name' => $name, 'sort_order' => $order]);
                }
            }
            $order = 0;
            foreach ($data['domains'] as $code => $name) {
                Domain::query()->firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ++$order]);
            }
            $domains = Domain::query()->pluck('id', 'code');

            foreach ($data['establishments'] as $code => $item) {
                // The establishment may already exist under another code (an alias): it is reused as is.
                $establishment = Establishment::query()->withTrashed()->whereIn('code', [$code, ...($item['aliases'] ?? [])])->orderByRaw('code = ? desc', [$code])->first()
                    ?? Establishment::query()->create(['code' => $code, 'name' => $item['name'], 'is_active' => true]);
                if ($establishment->trashed()) {
                    continue;
                }
                // Deleted mentions and programs count as existing: never recreated.
                $mentions = Mention::query()->withTrashed()->where('establishment_id', $establishment->id)->get();
                $programs = Program::query()->withTrashed()->where('establishment_id', $establishment->id)->get();
                foreach ($item['mentions'] as $name => $mention) {
                    $existing = $mentions->first(fn (Mention $other) => Columns::fold($other->name) === Columns::fold($name))
                        ?? $mentions->push(Mention::query()->create(['establishment_id' => $establishment->id, 'domain_id' => $mention['domain'] === null ? null : $domains[$mention['domain']] ?? null, 'name' => $name]))->last();
                    if ($existing->trashed()) {
                        continue;
                    }
                    foreach ($mention['programs'] as $program) {
                        if (! $programs->contains(fn (Program $other) => $other->mention_id === $existing->id && Columns::fold($other->name) === Columns::fold($program))) {
                            $programs->push(Program::query()->create(['establishment_id' => $establishment->id, 'mention_id' => $existing->id, 'name' => $program]));
                        }
                    }
                }
            }
        });
    }
}
