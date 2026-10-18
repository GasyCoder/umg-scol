<?php

namespace App\Actions\Imports;

use App\Enums\StudentImportRowStatus as RowStatus;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\StudentImport;
use App\Support\StudentImportColumns as Columns;

/**
 * Mentions and programs named by the valid rows of an import but missing from the establishment of
 * their row, created in that establishment only, inside the commit transaction (rolled back with it).
 * A new mention takes the domain of the file, itself created when given as "(CODE) Name".
 * Creations go through Eloquent, so each one is audited like a manual creation.
 */
class CreateImportReferences
{
    /** @return array{establishments: list<string>, levels: list<string>, domains: list<string>, mentions: list<string>, programs: list<string>} */
    public function handle(StudentImport $import): array
    {
        $wanted = $this->wanted($import);
        $created = ['establishments' => [], 'levels' => [], 'domains' => [], 'mentions' => [], 'programs' => []];
        // Establishments named by a global file and missing from the platform, then their mentions and programs.
        $codes = [];
        foreach ($wanted['establishments'] as $code => $name) {
            $existing = Establishment::query()->withTrashed()->where('code', $code)->lockForUpdate()->first();
            if ($existing === null) {
                $existing = Establishment::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
                $created['establishments'][] = $code.' — '.$name;
            }
            $codes[$code] = $existing->trashed() ? 0 : $existing->id;
        }
        foreach (['mentions', 'programs'] as $kind) {
            foreach ($wanted[$kind] as $key => $item) {
                if ($item['establishment_id'] === 0 && $item['establishment_code'] !== null) {
                    $wanted[$kind][$key]['establishment_id'] = $codes[$item['establishment_code']] ?? 0;
                }
            }
            $wanted[$kind] = array_filter($wanted[$kind], fn (array $item) => $item['establishment_id'] !== 0);
        }
        // Serialises reference creation per establishment (ordered locks): two imports never create the same mention twice.
        $ids = array_values(array_unique([...array_column($wanted['mentions'], 'establishment_id'), ...array_column($wanted['programs'], 'establishment_id')]));
        sort($ids);
        $establishments = Establishment::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        // A global import names the establishment of each creation.
        $where = fn (int $id) => $import->establishment_id === null ? $establishments[$id]->code.' › ' : '';

        foreach ($wanted['levels'] as $code) {
            // A trashed level keeps its code: never recreated, the rows stay unresolved and are skipped.
            if (! Level::query()->withTrashed()->where('code', $code)->lockForUpdate()->exists()) {
                [$name, $order] = Level::STANDARD[$code];
                Level::query()->create(['code' => $code, 'name' => $name, 'sort_order' => $order]);
                $created['levels'][] = $code;
            }
        }

        $domains = Domain::query()->lockForUpdate()->get();
        foreach ($wanted['domains'] as $code => $name) {
            if ($domains->first(fn (Domain $domain) => Columns::fold($domain->code) === Columns::fold($code)) === null) {
                $domains->push(Domain::query()->create(['code' => $code, 'name' => $name]));
                $created['domains'][] = '('.$code.') '.$name;
            }
        }

        $mentions = Mention::query()->whereIn('establishment_id', $ids)->lockForUpdate()->get();
        foreach ($wanted['mentions'] as $mention) {
            $siblings = $mentions->where('establishment_id', $mention['establishment_id'])->all();
            if ($this->named($siblings, $mention['name']) === null) {
                $domainId = $mention['domain_id'] ?? ($mention['domain_code'] === null ? null : $domains->first(fn (Domain $domain) => Columns::fold($domain->code) === Columns::fold($mention['domain_code']))?->id);
                $mentions->push(Mention::query()->create(['establishment_id' => $mention['establishment_id'], 'domain_id' => $domainId, 'name' => $mention['name']]));
                $created['mentions'][] = $where($mention['establishment_id']).$mention['name'];
            }
        }

        $programs = Program::query()->whereIn('establishment_id', $ids)->lockForUpdate()->get();
        foreach ($wanted['programs'] as $program) {
            $mention = $program['mention'] === null ? null : $this->named($mentions->where('establishment_id', $program['establishment_id'])->all(), $program['mention']);
            $siblings = $programs->where('establishment_id', $program['establishment_id'])->where('mention_id', $mention?->id)->all();
            if ($this->named($siblings, $program['name']) === null) {
                $programs->push(Program::query()->create(['establishment_id' => $program['establishment_id'], 'mention_id' => $mention?->id, 'name' => $program['name'], 'training_type' => $program['training_type']]));
                $created['programs'][] = $where($program['establishment_id']).($mention === null ? '' : $mention->name.' › ').$program['name'];
            }
        }

        return $created;
    }

    /**
     * What the valid rows ask for, without duplicates (names compared without case or accents).
     *
     * @return array{establishments: array<string,string>, levels: array<string,string>, domains: array<string,string>, mentions: array<string,array{establishment_id:int,establishment_code:string|null,name:string,domain_id:int|null,domain_code:string|null}>, programs: array<string,array{establishment_id:int,establishment_code:string|null,mention:string|null,name:string,training_type:string|null}>}
     */
    private function wanted(StudentImport $import): array
    {
        $wanted = ['establishments' => [], 'levels' => [], 'domains' => [], 'mentions' => [], 'programs' => []];
        $import->rows()->where('status', RowStatus::Valid)->chunkById((int) config('student_imports.chunk_size'), function ($rows) use (&$wanted) {
            foreach ($rows as $row) {
                $data = $row->normalized_data ?? [];
                if (is_string($data['_new_level'] ?? null) && isset(Level::STANDARD[$data['_new_level']])) {
                    $wanted['levels'][$data['_new_level']] = $data['_new_level'];
                }
                if (is_array($data['_new_domain'] ?? null)) {
                    $wanted['domains'][(string) $data['_new_domain']['code']] ??= (string) $data['_new_domain']['name'];
                }
                $establishment = (int) ($data['establishment_id'] ?? 0);
                $new = is_array($data['_new_establishment'] ?? null) ? (string) $data['_new_establishment']['code'] : null;
                if ($new !== null) {
                    $wanted['establishments'][$new] ??= (string) $data['_new_establishment']['name'];
                }
                $scope = $new === null ? (string) $establishment : 'new:'.$new;
                if (filled($data['_new_mention'] ?? null)) {
                    $wanted['mentions'][$scope.'|'.Columns::fold((string) $data['_new_mention'])] ??= [
                        'establishment_id' => $establishment,
                        'establishment_code' => $new,
                        'name' => (string) $data['_new_mention'],
                        'domain_id' => isset($data['_mention_domain_id']) ? (int) $data['_mention_domain_id'] : null,
                        'domain_code' => is_array($data['_new_domain'] ?? null) ? (string) $data['_new_domain']['code'] : null,
                    ];
                }
                if (filled($data['_new_program'] ?? null)) {
                    $mention = filled($data['mention'] ?? null) ? (string) preg_replace('/\s+/u', ' ', trim((string) $data['mention'])) : null;
                    $wanted['programs'][$scope.'|'.Columns::fold((string) $mention).'|'.Columns::fold((string) $data['_new_program'])] ??= [
                        'establishment_id' => $establishment,
                        'establishment_code' => $new,
                        'mention' => $mention, 'name' => (string) $data['_new_program'], 'training_type' => $data['_training_type'] ?? null,
                    ];
                }
            }
        });

        return $wanted;
    }

    /**
     * @template T of Mention|Program
     *
     * @param  array<int,T>  $items
     * @return T|null
     */
    private function named(array $items, string $value): Mention|Program|null
    {
        $matches = array_values(array_filter($items, fn ($item) => (filled($item->code) && Columns::fold($item->code) === Columns::fold($value)) || Columns::fold($item->name) === Columns::fold($value)));

        return $matches[0] ?? null;
    }
}
