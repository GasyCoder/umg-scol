<?php

namespace App\Support;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentImportColumns
{
    /** @return array<string,string> */
    public static function labels(): array
    {
        return ['last_name' => 'Nom', 'first_names' => 'Prénoms', 'student_number' => 'Matricule', 'birth_date' => 'Date de naissance', 'birth_place' => 'Lieu de naissance', 'cin' => 'CIN', 'cin_issued_at' => 'Date de délivrance CIN', 'cin_issued_place' => 'Lieu de délivrance CIN', 'phone' => 'Téléphone', 'email' => 'Email', 'address' => 'Adresse', 'bac_registration_number' => 'Numéro BAC', 'bac_series' => 'Série BAC', 'bac_year' => 'Année BAC', 'bac_mention' => 'Mention BAC', 'level' => 'Niveau', 'program' => 'Parcours'];
    }

    public static function fold(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /**
     * @param  array<int,string>  $headers
     * @return array<string,int>
     */
    public static function suggest(array $headers): array
    {
        $aliases = ['nom' => 'last_name', 'nom_etudiant' => 'last_name', 'prenom' => 'first_names', 'prenoms' => 'first_names', 'matricule' => 'student_number', 'n_matricule' => 'student_number', 'numero_matricule' => 'student_number', 'n_bac' => 'bac_registration_number', 'numero_bac' => 'bac_registration_number', 'n_inscription_bacc' => 'bac_registration_number'];
        foreach (self::labels() as $field => $label) {
            $aliases[self::header($label)] = $field;
            $aliases[$field] = $field;
        }
        $mapping = [];
        foreach ($headers as $index => $header) {
            $field = $aliases[self::header($header)] ?? null;
            if ($field !== null && ! isset($mapping[$field])) {
                $mapping[$field] = $index;
            }
        }

        return $mapping;
    }

    private static function header(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', self::fold(str_replace(['°', 'º'], '', $value))), '_');
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
            if (in_array($field, ['birth_date', 'cin_issued_at'], true) && filled($value)) {
                if (is_numeric($value) && (float) $value > 0 && (float) $value < 2958466) {
                    $value = Date::excelToDateTimeObject((float) $value, null, $calendar)->format('Y-m-d');
                } elseif (is_string($value) && preg_match('/^(\d{2})[\/](\d{2})[\/](\d{4})$/', $value, $parts)) {
                    $value = $parts[3].'-'.$parts[2].'-'.$parts[1];
                }
            } elseif ($value !== null && is_scalar($value)) {
                $value = (string) $value;
            }
            $data[$field] = $value;
        }

        return StudentNormalization::data($data);
    }
}
