<?php

namespace App\Support;

use App\Enums\ImportKind;
use App\Models\StudentImportRow;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Columns of the imported files: the official establishment file (modèle établissement, in the
 * template order), plus the PNES scholarship base (code bourse, UIN, bank account) and the
 * pre-registration results (file number, result) for their own kinds.
 * BOURSIER and TAUX DE BOURSE are spreadsheet formulas: never imported, the server applies the rule itself.
 */
class StudentImportColumns
{
    public const DATE_FIELDS = ['birth_date', 'cin_issued_at', 'scholarship_requested_at'];

    /** Identity fields written to Student. */
    public const IDENTITY_FIELDS = ['last_name', 'first_names', 'sex', 'birth_date', 'mother_name', 'cin', 'cin_issued_at', 'cin_issued_place', 'uin', 'scholarship_code', 'bank_account', 'nationality', 'bac_year', 'bac_series', 'address', 'phone', 'email'];

    /** Annual declaration fields written to StudentRecord. */
    public const RECORD_FIELDS = ['repeat_code', 'scholarship_requested_at', 'scholarship_request_reference'];

    /** @return array<string,string> */
    public static function labels(?ImportKind $kind = null): array
    {
        $extra = match ($kind) {
            ImportKind::Pnes => ['scholarship_code' => 'Code bourse', 'uin' => 'UIN', 'bank_account' => 'Numéro de compte'],
            ImportKind::Preinscription => ['preinscription_reference' => 'Numéro de dossier', 'result' => 'Résultat'],
            default => [],
        };

        return [...$extra, ...self::establishmentLabels()];
    }

    /** @return array<string,string> */
    private static function establishmentLabels(): array
    {
        return [
            'last_name' => 'Nom', 'first_names' => 'Prénoms', 'sex' => 'Sexe', 'birth_date' => 'Date de naissance',
            'mother_name' => 'Nom et prénoms de la mère', 'cin' => 'CIN', 'cin_issued_at' => 'Date de délivrance CIN',
            'cin_issued_place' => 'Lieu de délivrance CIN', 'nationality' => 'Nationalité', 'bac_year' => 'Année d’obtention du BAC',
            'bac_series' => 'Série du BAC', 'repeat_code' => 'Code de redoublement',
            'scholarship_requested_at' => 'Date de dépôt de la demande de bourse', 'scholarship_request_reference' => 'Référence demande de bourse',
            'address' => 'Adresse exacte', 'phone' => 'Numéro téléphone', 'email' => 'Adresse mail',
            'sector' => 'Institution', 'domain' => 'Domaine', 'mention' => 'Mention', 'program' => 'Parcours',
            'training_type' => 'Type de formation', 'level' => 'Niveau', 'establishment' => 'École / Institut',
            'establishment_code' => 'Code établissement',
        ];
    }

    /**
     * Columns the analysis cannot do without.
     *
     * @return list<string>
     */
    public static function required(?ImportKind $kind = null): array
    {
        // Sex and nationality are checked when the file has them; some establishment files do not.
        return match ($kind) {
            ImportKind::Pnes => ['scholarship_code', 'last_name', 'birth_date', 'repeat_code', 'level'],
            // New bacheliers: first year, new students when the file says nothing.
            ImportKind::Preinscription => ['last_name', 'birth_date'],
            default => ['last_name', 'birth_date', 'repeat_code', 'level'],
        };
    }

    public static function fold(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /**
     * @param  array<int,string>  $headers
     * @return array<string,int>
     */
    public static function suggest(array $headers, ?ImportKind $kind = null): array
    {
        // Headers of the official template, including its spelling variants.
        $aliases = [
            'nom_etudiant' => 'last_name', 'prenom' => 'first_names', 'genre' => 'sex',
            'nom_et_prenom_de_mere' => 'mother_name', 'nom_et_prenoms_de_mere' => 'mother_name', 'nom_et_prenom_de_la_mere' => 'mother_name', 'nom_de_la_mere' => 'mother_name', 'complement_nom' => 'mother_name',
            'date_delivrabce' => 'cin_issued_at', 'date_delivrance' => 'cin_issued_at', 'date_de_delivrance' => 'cin_issued_at',
            'lieu_de_delivrance' => 'cin_issued_place', 'lieu_delivrance' => 'cin_issued_place',
            'annee_d_obtention_du_bacc' => 'bac_year', 'annee_d_obtention_du_bac' => 'bac_year', 'annee_bac' => 'bac_year', 'annee_du_bac' => 'bac_year', 'annee_du_bacc' => 'bac_year', 'annee_bacc' => 'bac_year',
            'serie_du_bacc' => 'bac_series', 'serie_bac' => 'bac_series', 'serie_bacc' => 'bac_series',
            'code_redoublement' => 'repeat_code',
            'date_de_depot_du_demande_de_bourse' => 'scholarship_requested_at', 'date_de_depot_de_la_demande_de_bourse' => 'scholarship_requested_at',
            'reference_demande_de_bourse' => 'scholarship_request_reference', 'reference_de_la_demande_de_bourse' => 'scholarship_request_reference',
            'adresse' => 'address', 'telephone' => 'phone', 'numero_telephone' => 'phone', 'email' => 'email', 'mail' => 'email',
            'ecole_institut' => 'establishment', 'etablissement' => 'establishment', 'nom_etablissement' => 'establishment',
            'institution_code' => 'establishment_code', 'code_institution' => 'establishment_code', 'code_etablissement' => 'establishment_code', 'sigle' => 'establishment_code', 'sigle_etablissement' => 'establishment_code',
            'nom_mere' => 'mother_name', 'numero_cin' => 'cin', 'n_cin' => 'cin', 'numero_de_telephone' => 'phone', 'e_mail' => 'email', 'courriel' => 'email',
            'date_naissance' => 'birth_date', 'prenoms_etudiant' => 'first_names',
            'code_bourse' => 'scholarship_code', 'numero_de_compte' => 'bank_account', 'n_compte' => 'bank_account', 'compte' => 'bank_account', 'rib' => 'bank_account',
            'numero_de_dossier' => 'preinscription_reference', 'n_dossier' => 'preinscription_reference', 'dossier' => 'preinscription_reference', 'resultat' => 'result', 'decision' => 'result',
        ];
        $labels = self::labels($kind);
        foreach ($labels as $field => $label) {
            $aliases[self::header($label)] = $field;
            $aliases[$field] = $field;
        }
        $mapping = [];
        foreach ($headers as $index => $header) {
            $field = $aliases[self::header($header)] ?? null;
            if ($field !== null && isset($labels[$field]) && ! isset($mapping[$field])) {
                $mapping[$field] = $index;
            }
        }

        // "Institution" next to "Institution Code" names the establishment, not public/private.
        if (isset($mapping['establishment_code'], $mapping['sector']) && ! isset($mapping['establishment']) && self::header($headers[$mapping['sector']]) === 'institution') {
            $mapping['establishment'] = $mapping['sector'];
            unset($mapping['sector']);
        }

        return $mapping;
    }

    private static function header(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', self::fold(str_replace(['°', 'º'], '', $value))), '_');
    }

    /**
     * Legend line of the template ("[ 1 ]", "[ 2 ]"… under the headers): not a student.
     *
     * @param  array<int,mixed>  $raw
     */
    public static function isLegendRow(array $raw): bool
    {
        return count(array_filter($raw, fn ($value) => is_string($value) && preg_match('/^\[\s*\d+\s*\]/', trim($value)) === 1)) >= 3;
    }

    /**
     * First line of the summary block at the bottom of the template ("Arrêté au nombre de : …").
     * This line and every following one are totals, not students.
     *
     * @param  array<int,mixed>  $raw
     */
    public static function isSummaryStart(array $raw): bool
    {
        foreach ($raw as $value) {
            if (is_string($value) && str_starts_with(self::fold($value), 'arrete au nombre')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The file row with the values corrected in the preview, each put in the column of its field.
     *
     * @param  array<int,mixed>  $raw
     * @param  array<string,int>  $mapping
     * @param  array<string,string>|null  $corrections
     * @return array<int,mixed>
     */
    public static function corrected(array $raw, array $mapping, ?array $corrections): array
    {
        foreach ($corrections ?? [] as $field => $value) {
            if (isset($mapping[$field])) {
                $raw[$mapping[$field]] = $value === '' ? null : $value;
            }
        }

        return $raw;
    }

    /**
     * Values of a staged row as analysed: file values, corrections made in the preview, and the
     * fields of the existing record the agent chose to replace.
     *
     * @param  array<string,int>  $mapping
     * @return array<string,mixed>
     */
    public static function preview(StudentImportRow $row, array $mapping, int $calendar): array
    {
        $data = self::normalize(self::corrected($row->raw_data, $mapping, $row->corrections), $mapping, $calendar);
        $updates = array_values(array_intersect($row->identity_updates ?? [], array_keys($mapping)));
        if ($updates !== []) {
            $data['_identity_updates'] = $updates;
        }

        return $data;
    }

    /**
     * @param  array<int,mixed>  $raw
     * @param  array<string,int>  $mapping
     * @return array<string,mixed>
     */
    public static function normalize(array $raw, array $mapping, int $calendar = Date::CALENDAR_WINDOWS_1900): array
    {
        $data = [];
        foreach ($mapping as $field => $index) {
            $value = $raw[$index] ?? null;
            if (in_array($field, self::DATE_FIELDS, true) && filled($value)) {
                if (is_numeric($value) && (float) $value > 0 && (float) $value < 2958466) {
                    $value = Date::excelToDateTimeObject((float) $value, null, $calendar)->format('Y-m-d');
                } elseif (is_string($value) && preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', trim($value), $parts)) {
                    $value = $parts[3].'-'.str_pad($parts[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                }
            } elseif (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
                // Numeric cells (CIN, phone, reference) are read as floats: keep every digit.
                $value = sprintf('%.0f', $value);
            } elseif ($value !== null && is_scalar($value)) {
                $value = (string) $value;
            }
            $data[$field] = $value;
        }

        return StudentNormalization::data($data);
    }
}
