<?php

namespace App\Support\References;

use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Classifies each imported row against the current referential and the actor's perimeter:
 * create, update (same establishment + code, name or mention differs), unchanged, or error with a French reason.
 * Run once for the preview and again inside the confirmation transaction.
 */
final class ReferenceImportAnalyzer
{
    public const STATUSES = ['create', 'update', 'unchanged', 'error'];

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $input
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function analyze(User $user, string $kind, array $input): array
    {
        $programs = $kind === 'programs';
        $rows = array_map(fn (array $row): array => $this->normalize($row, $programs), $input);

        $establishments = Establishment::query()->whereIn('code', array_unique(array_filter(array_column($rows, 'establishment_code'))))
            ->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all();
        $perimeter = array_values(ReferenceTransfer::establishments($user)->whereIn('id', $establishments)->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $byCode = $byName = [];
        foreach ($this->existing($kind, $perimeter) as $record) {
            if ($record['code'] !== null) {
                $byCode[$record['establishment_id'].'|'.$record['code']] = $record;
            }
            $byName[$record['establishment_id'].'|'.mb_strtolower($record['name'])][] = $record;
        }
        $mentionByCode = $codedMentions = [];
        if ($programs) {
            foreach (Mention::query()->whereIn('establishment_id', $perimeter)->whereNotNull('code')->get(['id', 'establishment_id', 'code']) as $mention) {
                $mentionByCode[$mention->establishment_id.'|'.$mention->code] = $mention->id;
                $codedMentions[$mention->id] = true;
            }
        }

        $model = ReferenceTransfer::model($kind);
        $reserved = $model::query()->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull('deleted_at')->whereIn('establishment_id', $perimeter)->whereNotNull('code')->get(['establishment_id', 'code'])->mapWithKeys(fn ($r) => [$r->getAttribute('establishment_id').'|'.$r->getAttribute('code') => true])->all();
        $duplicates = $this->fileDuplicates($rows);
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $i => $row) {
            $errors = [];
            $found = $establishments[$row['establishment_code']] ?? null;
            $establishmentId = $found !== null && in_array($found, $perimeter, true) ? $found : null;
            if ($row['establishment_code'] === '') {
                $errors[] = 'Code établissement manquant.';
            } elseif ($found === null) {
                $errors[] = 'Code établissement inconnu.';
            } elseif ($establishmentId === null) {
                $errors[] = 'Établissement hors de votre périmètre.';
            }
            if ($row['name'] === '') {
                $errors[] = 'Nom manquant.';
            } elseif (mb_strlen($row['name']) > ReferenceTransfer::NAME_MAX) {
                $errors[] = 'Nom trop long ('.ReferenceTransfer::NAME_MAX.' caractères maximum).';
            }
            if ($row['code'] !== '' && mb_strlen($row['code']) > ReferenceTransfer::CODE_MAX) {
                $errors[] = 'Code trop long ('.ReferenceTransfer::CODE_MAX.' caractères maximum).';
            } elseif ($row['code'] !== '' && preg_match(ReferenceTransfer::CODE_PATTERN, $row['code']) !== 1) {
                $errors[] = 'Code invalide : lettres, chiffres, points, tirets ou underscores uniquement.';
            }
            if ($establishmentId !== null && isset($reserved[$establishmentId.'|'.$row['code']])) {
                $errors[] = 'Ce code est réservé par un élément en corbeille. Restaurez-le avant de l’importer.';
            }
            $mentionId = null;
            if ($programs && $row['mention_code'] !== '' && $establishmentId !== null) {
                $mentionId = $mentionByCode[$establishmentId.'|'.$row['mention_code']] ?? null;
                if ($mentionId === null) {
                    $errors[] = 'Code mention inconnu pour cet établissement.';
                }
            }
            if (isset($duplicates[$i])) {
                $errors[] = $duplicates[$i];
            }

            $result = ['status' => 'error', 'reason' => implode(' ', $errors), 'record_id' => null, 'mention_id' => $mentionId];
            if ($errors === [] && $establishmentId !== null) {
                $record = $row['code'] !== '' ? ($byCode[$establishmentId.'|'.$row['code']] ?? null) : null;
                $sameName = $byName[$establishmentId.'|'.mb_strtolower($row['name'])] ?? [];
                $result = $this->classify($row, $record, $sameName, $mentionId, $programs, $codedMentions);
            }
            $rows[$i] = [...$row, ...$result, 'establishment_id' => $establishmentId];
            $counts[$result['status']]++;
        }

        return ['rows' => $rows, 'counts' => [...$counts, 'total' => count($rows)]];
    }

    /**
     * Stable digest of what the confirmation would write; a change means the preview is stale.
     *
     * @param  array{rows: list<array<string, mixed>>, counts: array<string, int>}  $analysis
     */
    public static function fingerprint(array $analysis): string
    {
        return hash('sha256', json_encode(array_map(fn (array $row) => [
            $row['line'], $row['status'], $row['record_id'], $row['establishment_id'], $row['mention_id'], $row['code'], $row['name'],
        ], $analysis['rows']), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{id: int, establishment_id: int, mention_id: int|null, code: string|null, name: string}|null  $record  same establishment and code
     * @param  list<array{id: int, establishment_id: int, mention_id: int|null, code: string|null, name: string}>  $sameName  same establishment and name (case-insensitive)
     * @param  array<int, true>  $codedMentions
     * @return array{status: string, reason: string, record_id: int|null, mention_id: int|null}
     */
    private function classify(array $row, ?array $record, array $sameName, ?int $mentionId, bool $programs, array $codedMentions): array
    {
        if ($record !== null) {
            // A mention without code cannot be written in the file: an empty cell keeps it.
            if ($programs && $mentionId === null && $record['mention_id'] !== null && ! isset($codedMentions[$record['mention_id']])) {
                $mentionId = $record['mention_id'];
            }
            $changes = array_filter([
                $record['name'] !== $row['name'] ? 'Nom modifié.' : null,
                $programs && $record['mention_id'] !== $mentionId ? 'Mention modifiée.' : null,
            ]);

            return ['status' => $changes === [] ? 'unchanged' : 'update', 'reason' => implode(' ', $changes), 'record_id' => $record['id'], 'mention_id' => $mentionId];
        }
        $withoutCode = array_filter($sameName, fn (array $r): bool => $r['code'] === null && (! $programs || $r['mention_id'] === $mentionId));
        if ($row['code'] === '') {
            foreach ($withoutCode as $r) {
                if ($r['name'] === $row['name']) {
                    return ['status' => 'unchanged', 'reason' => 'Déjà présent, sans code.', 'record_id' => $r['id'], 'mention_id' => $mentionId];
                }
            }
            if ($sameName !== []) {
                return ['status' => 'error', 'reason' => 'Doublon probable : ce nom existe déjà dans l’établissement. Indiquez son code pour le mettre à jour.', 'record_id' => null, 'mention_id' => $mentionId];
            }
        } elseif ($withoutCode !== []) {
            return ['status' => 'error', 'reason' => 'Doublon probable : ce nom existe déjà sans code. Ajoutez le code depuis le formulaire de modification.', 'record_id' => null, 'mention_id' => $mentionId];
        }

        return ['status' => 'create', 'reason' => '', 'record_id' => null, 'mention_id' => $mentionId];
    }

    /**
     * @param  list<int>  $perimeter
     * @return list<array{id: int, establishment_id: int, mention_id: int|null, code: string|null, name: string}>
     */
    private function existing(string $kind, array $perimeter): array
    {
        if ($kind === 'mentions') {
            return array_values(Mention::query()->whereIn('establishment_id', $perimeter)->get(['id', 'establishment_id', 'code', 'name'])
                ->map(fn (Mention $r): array => ['id' => $r->id, 'establishment_id' => $r->establishment_id, 'mention_id' => null, 'code' => $r->code, 'name' => $r->name])->all());
        }

        return array_values(Program::query()->whereIn('establishment_id', $perimeter)->get(['id', 'establishment_id', 'mention_id', 'code', 'name'])
            ->map(fn (Program $r): array => ['id' => $r->id, 'establishment_id' => $r->establishment_id, 'mention_id' => $r->mention_id, 'code' => $r->code, 'name' => $r->name])->all());
    }

    /**
     * Rows sharing an establishment and a code (or, without code, a name) are all rejected: the file is ambiguous.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function fileDuplicates(array $rows): array
    {
        $groups = [];
        foreach ($rows as $i => $row) {
            if ($row['establishment_code'] === '' || ($row['name'] === '' && $row['code'] === '')) {
                continue;
            }
            $key = $row['code'] !== '' ? 'code|'.$row['establishment_code'].'|'.$row['code'] : 'name|'.$row['establishment_code'].'|'.mb_strtolower($row['name']);
            $groups[$key][] = $i;
        }
        $messages = [];
        foreach ($groups as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $lines = implode(', ', array_map(fn (int $i) => $rows[$i]['line'], $indexes));
            foreach ($indexes as $i) {
                $messages[$i] = str_starts_with($key, 'code|')
                    ? 'Code présent plusieurs fois dans le fichier (lignes '.$lines.').'
                    : 'Doublon probable : même nom sans code plusieurs fois dans le fichier (lignes '.$lines.').';
            }
        }

        return $messages;
    }

    /**
     * @param  array{line: int, values: array<string, string>}  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row, bool $programs): array
    {
        $value = fn (string $key): string => trim($row['values'][$key] ?? '');

        return [
            'line' => $row['line'],
            'establishment_code' => mb_strtoupper($value('establishment')),
            ...($programs ? ['mention_code' => mb_strtoupper($value('mention'))] : []),
            'code' => mb_strtoupper($value('code')),
            'name' => $value('name'),
        ];
    }
}
