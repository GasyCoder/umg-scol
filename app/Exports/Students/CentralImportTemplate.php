<?php

namespace App\Exports\Students;

use App\Enums\ImportKind;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Empty models of the central office files: the PNES scholarship base (its own headers) and the
 * standard pre-registration results of new bacheliers. Every cell is text, so that 18-digit UIN,
 * account numbers and CIN keep all their digits.
 */
final class CentralImportTemplate
{
    /** @return list<string> */
    public static function headers(ImportKind $kind): array
    {
        return match ($kind) {
            ImportKind::Pnes => ['Code Bourse', 'UIN', 'Numero de Compte', 'Nom', 'Prenom', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Code Redoublement'],
            ImportKind::Preinscription => ['Numero de dossier', 'Nom', 'Prenom', 'Sexe', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Nationalité', 'Série du BAC', 'Année du BAC', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Resultat'],
            ImportKind::Establishment => [],
        };
    }

    public static function download(ImportKind $kind): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($kind === ImportKind::Pnes ? 'Boursiers' : 'Préinscription');
        $headers = self::headers($kind);
        $sheet->fromArray([$headers]);
        $last = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->getStyle("A2:{$last}5001")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return EstablishmentTemplateWorkbook::download($book, $kind === ImportKind::Pnes ? 'modele-base-pnes.xlsx' : 'modele-preinscription.xlsx');
    }
}
