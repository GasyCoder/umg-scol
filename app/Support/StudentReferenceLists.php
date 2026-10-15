<?php

namespace App\Support;

/** Closed lists of the establishment file (drop-down values of the official template). */
final class StudentReferenceLists
{
    public const NATIONALITIES = ['Malagasy', 'Comorien', 'Africain', 'Européen', 'Asiatique', 'Mauricien', 'Autres'];

    public const BAC_SERIES = ['A1', 'A2', 'C', 'D', 'L', 'S', 'OSE', 'Tech. Génie civil', 'Tech. Industriel', 'Tech. Tertiaire', 'Tech. Agricole', 'Technologique', 'Autres'];

    /**
     * Canonical spelling of a listed value, matched without case or accents; the value itself otherwise.
     *
     * @param  list<string>  $list
     */
    public static function canonical(array $list, string $value): string
    {
        foreach ($list as $item) {
            if (StudentImportColumns::fold($item) === StudentImportColumns::fold($value)) {
                return $item;
            }
        }

        return $value;
    }
}
