<?php

namespace App\Exports\Students;

use App\Enums\EstablishmentSector;
use App\Enums\RepeatCode;
use App\Enums\TrainingType;
use App\Models\AcademicYear;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\StudentRecord;
use App\Support\ScholarshipEligibility;
use App\Support\StudentReferenceLists;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The establishment file (modèle établissement), columns A to Z in the official order, headers
 * and colours, drop-down lists and the BOURSIER/TAUX formulas. Empty, it is the import model;
 * filled with declarations, it is the export, which the import reads back as is.
 * Only the first sheet is imported; the hidden "Listes" sheet feeds the drop-downs.
 */
final class EstablishmentTemplateWorkbook
{
    /** Header text of the official file, by column (the "Date delivrabce" typo is corrected). */
    public const HEADERS = [
        'A' => 'NOM', 'B' => 'PRENOMS', 'C' => 'SEXE', 'D' => 'DATE DE NAISSANCE', 'E' => 'Nom et prénom de mère',
        'F' => 'CIN', 'G' => 'Date de délivrance', 'H' => 'Lieu de délivrance', 'I' => 'NATIONALITÉ',
        'J' => 'ANNÉE D’OBTENTION DU BACC', 'K' => 'SÉRIE DU BACC', 'L' => 'CODE DE REDOUBLEMENT', 'M' => 'BOURSIER',
        'N' => 'TAUX DE BOURSE', 'O' => 'DATE DE DEPÔT DU DEMANDE DE BOURSE', 'P' => 'REFERENCE DEMANDE DE BOURSE',
        'Q' => 'ADRESSE EXACTE', 'R' => 'NUMERO TELEPHONE', 'S' => 'ADRESSE MAIL', 'T' => 'Institution', 'U' => 'Domaine',
        'V' => 'Mention', 'W' => 'Parcours', 'X' => 'Type de formation', 'Y' => 'Niveau', 'Z' => 'Ecole / Institut',
    ];

    /** Header colours of the official file. */
    private const FILLS = [
        'D9D9D9' => ['A', 'B', 'C', 'D', 'F', 'G', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'],
        'FCE5DB' => ['E'], 'F4C7AE' => ['H'], '93D17A' => ['Q', 'R', 'S', 'V', 'W'],
        'FF0000' => ['T', 'U', 'X', 'Y'], '00B0F0' => ['Z'],
    ];

    private const WIDTHS = [
        'A' => 22, 'B' => 24, 'C' => 8, 'D' => 13, 'E' => 30, 'F' => 16, 'G' => 13, 'H' => 18, 'I' => 13, 'J' => 12,
        'K' => 12, 'L' => 20, 'M' => 11, 'N' => 10, 'O' => 14, 'P' => 14, 'Q' => 28, 'R' => 15, 'S' => 32, 'T' => 12,
        'U' => 34, 'V' => 22, 'W' => 22, 'X' => 16, 'Y' => 9, 'Z' => 48,
    ];

    /** Hidden sheet of drop-down values, the only extra sheet the import accepts. */
    public const LISTS_SHEET = 'Listes';

    /** Rows offered with drop-downs and formulas in the empty model. */
    public const MODEL_ROWS = 1000;

    public function __construct(private ?Establishment $establishment, private ?AcademicYear $year) {}

    /** Private XLSX download; formulas are computed by the spreadsheet software when opened. */
    public static function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    /** Label of an establishment in column Z: "Name (CODE)". */
    public static function establishmentLabel(Establishment $establishment): string
    {
        return $establishment->name.' ('.$establishment->code.')';
    }

    /** "(CODE) Name", as in the official file. */
    public static function domainLabel(Domain $domain): string
    {
        return '('.$domain->code.') '.$domain->name;
    }

    /**
     * One declaration as a line of the file. BOURSIER/TAUX hold the server rule, not formulas.
     * The record needs student, academicYear, establishment, level and program.mention.domain loaded.
     *
     * @return array<string,string|int|\DateTimeInterface|null>
     */
    public static function rowFor(StudentRecord $record): array
    {
        $student = $record->student;
        $eligibility = ScholarshipEligibility::evaluate($student, $record);
        $domain = $record->program?->mention?->domain;

        return [
            'A' => $student->last_name, 'B' => $student->first_names, 'C' => $student->sex?->value, 'D' => $student->birth_date,
            'E' => $student->mother_name, 'F' => $student->cin, 'G' => $student->cin_issued_at, 'H' => $student->cin_issued_place,
            'I' => $student->nationality, 'J' => $student->bac_year, 'K' => $student->bac_series, 'L' => $record->repeat_code?->label(),
            'M' => match ($eligibility['status']) {
                ScholarshipEligibility::ELIGIBLE => 'OUI',
                ScholarshipEligibility::NOT_ELIGIBLE => 'NON',
                default => null,
            },
            'N' => $eligibility['status'] === ScholarshipEligibility::UNDETERMINED ? null : $eligibility['rate'],
            'O' => $record->scholarship_requested_at, 'P' => $record->scholarship_request_reference,
            'Q' => $student->address, 'R' => $student->phone, 'S' => $student->email,
            'T' => $record->establishment->sector?->label(), 'U' => $domain === null ? null : self::domainLabel($domain),
            'V' => $record->program?->mention?->name, 'W' => $record->program?->name, 'X' => $record->program?->training_type?->label(),
            'Y' => $record->level->code, 'Z' => self::establishmentLabel($record->establishment),
        ];
    }

    /**
     * @param  list<array<string,string|int|\DateTimeInterface|null>>  $rows  values by column letter
     */
    public function build(array $rows = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('UMG')->setTitle('Données étudiants');
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($this->establishment->code ?? 'Étudiants', 0, 31));
        $lists = $this->lists($book);

        foreach (self::HEADERS as $column => $header) {
            $sheet->setCellValue($column.'1', $header);
            $sheet->getColumnDimension($column)->setWidth(self::WIDTHS[$column]);
        }
        $header = $sheet->getStyle('A1:Z1');
        $header->getFont()->setBold(true);
        $header->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $header->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (self::FILLS as $color => $columns) {
            foreach ($columns as $column) {
                $sheet->getStyle($column.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
        }
        $sheet->getRowDimension(1)->setRowHeight(48);
        $sheet->freezePane('C2');

        $last = 1 + max(self::MODEL_ROWS, count($rows));
        foreach ($rows as $index => $values) {
            $this->writeRow($sheet, $index + 2, $values);
        }
        if ($rows === []) {
            $this->formulas($sheet, $last);
        }
        $this->formats($sheet, $last);
        $this->validations($sheet, $last, $lists);
        $sheet->setAutoFilter('A1:Z'.$last);
        if ($rows !== []) {
            $this->summary($sheet, count($rows) + 1);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** @param array<string,string|int|\DateTimeInterface|null> $values */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                $sheet->setCellValue($column.$row, Date::PHPToExcel($value));
            } elseif (is_int($value)) {
                $sheet->setCellValue($column.$row, $value);
            } else {
                // Text only: identifiers keep every digit and a value starting with "=" never becomes a formula.
                $sheet->setCellValueExplicit($column.$row, $value, DataType::TYPE_STRING);
            }
        }
    }

    /** BOURSIER and TAUX DE BOURSE: same rule as the server (ScholarshipEligibility), for the model only. */
    private function formulas(Worksheet $sheet, int $last): void
    {
        $limit = $this->year?->scholarship_max_bac_year;
        if ($limit === null) {
            return;
        }
        for ($r = 2; $r <= $last; $r++) {
            $sheet->setCellValue('M'.$r, "=IF(A{$r}=\"\",\"\",IF(AND(I{$r}=\"Malagasy\",J{$r}<>\"\",J{$r}<={$limit},P{$r}<>\"\",OR(L{$r}=\"N (Nouveau)\",L{$r}=\"P (Passant)\",L{$r}=\"R (Redoublant)\")),\"OUI\",\"NON\"))");
            $sheet->setCellValue('N'.$r, "=IF(M{$r}=\"OUI\",IF(L{$r}=\"R (Redoublant)\",0.25,1),IF(M{$r}=\"\",\"\",0))");
        }
    }

    private function formats(Worksheet $sheet, int $last): void
    {
        foreach (['D', 'G', 'O'] as $column) {
            $sheet->getStyle("{$column}2:{$column}{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }
        // Text cells: Excel never drops a leading zero nor rounds a 12-digit CIN.
        foreach (['F', 'P', 'R'] as $column) {
            $sheet->getStyle("{$column}2:{$column}{$last}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
        $sheet->getStyle("N2:N{$last}")->getNumberFormat()->setFormatCode('# ?/?');
    }

    /** @param array<string,string> $lists column => list range */
    private function validations(Worksheet $sheet, int $last, array $lists): void
    {
        $closed = [
            'C' => '"M,F"', 'I' => $lists['nationalities'], 'K' => $lists['series'], 'L' => $lists['repeat_codes'],
            'T' => $lists['sectors'], 'X' => $lists['training_types'], 'Y' => $lists['levels'],
        ];
        // Referentials of the establishment: proposed, a missing entry is reported by the import.
        $open = array_filter(['U' => $lists['domains'] ?? null, 'V' => $lists['mentions'] ?? null, 'W' => $lists['programs'] ?? null, 'Z' => $lists['establishment'] ?? null]);
        foreach ([...$closed, ...$open] as $column => $source) {
            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)->setFormula1($source)->setAllowBlank(true)->setShowDropDown(true)
                ->setShowErrorMessage(true)->setErrorStyle(isset($closed[$column]) ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                ->setErrorTitle('Valeur hors liste')->setError('Choisissez une valeur de la liste du modèle.');
            $sheet->setDataValidation("{$column}2:{$column}{$last}", $validation);
        }
        $year = new DataValidation;
        $year->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1('1900')->setFormula2((string) now()->year)
            ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorTitle('Année invalide')->setError('Année sur 4 chiffres.');
        $sheet->setDataValidation("J2:J{$last}", $year);
        foreach (['D', 'G', 'O'] as $column) {
            $date = new DataValidation;
            $date->setType(DataValidation::TYPE_DATE)->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1('1')->setFormula2((string) (int) Date::PHPToExcel(now()))
                ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorTitle('Date invalide')->setError('Saisissez une date (jj/mm/aaaa), non future.');
            $sheet->setDataValidation("{$column}2:{$column}{$last}", $date);
        }
    }

    /**
     * Hidden sheet with the drop-down values.
     *
     * @return array<string,string> list name => absolute range
     */
    private function lists(Spreadsheet $book): array
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(self::LISTS_SHEET);
        $establishmentId = $this->establishment?->id;
        $columns = [
            'nationalities' => StudentReferenceLists::NATIONALITIES,
            'series' => StudentReferenceLists::BAC_SERIES,
            'repeat_codes' => array_map(fn (RepeatCode $code) => $code->label(), RepeatCode::cases()),
            'sectors' => array_map(fn (EstablishmentSector $sector) => $sector->label(), EstablishmentSector::cases()),
            'training_types' => array_map(fn (TrainingType $type) => $type->label(), TrainingType::cases()),
            'levels' => Level::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->pluck('code')->all(),
            'domains' => Domain::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get()->map(fn (Domain $domain) => self::domainLabel($domain))->all(),
        ];
        if ($this->establishment !== null) {
            $columns['mentions'] = Mention::query()->where('establishment_id', $establishmentId)->where('is_active', true)->orderBy('name')->pluck('name')->unique()->values()->all();
            $columns['programs'] = Program::query()->where('establishment_id', $establishmentId)->where('is_active', true)->orderBy('name')->pluck('name')->unique()->values()->all();
            $columns['establishment'] = [self::establishmentLabel($this->establishment)];
        }
        $ranges = [];
        $index = 1;
        foreach ($columns as $name => $values) {
            if ($values === []) {
                continue;
            }
            $column = Coordinate::stringFromColumnIndex($index++);
            foreach (array_values($values) as $row => $value) {
                $sheet->setCellValueExplicit($column.($row + 1), (string) $value, DataType::TYPE_STRING);
            }
            $ranges[$name] = self::LISTS_SHEET."!\${$column}\$1:\${$column}\$".count($values);
        }
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return $ranges;
    }

    /** "Arrêté au nombre de" block of the official file, under the data (ignored by the import). */
    private function summary(Worksheet $sheet, int $lastData): void
    {
        $start = $lastData + 3;
        $range = fn (string $column) => "\${$column}\$2:\${$column}\${$lastData}";
        $sheet->setCellValue("A{$start}", 'Arrêté au nombre de:');
        $sheet->setCellValue("C{$start}", '=SUM(C'.($start + 1).':C'.($start + 3).')');
        $sheet->setCellValue("E{$start}", 'Situation boursier :');
        $sheet->setCellValue("F{$start}", '=SUM(F'.($start + 1).':F'.($start + 2).')');
        foreach (RepeatCode::cases() as $offset => $code) {
            $sheet->setCellValue('A'.($start + 1 + $offset), $code->label());
            $sheet->setCellValue('C'.($start + 1 + $offset), '=COUNTIF('.$range('L').',"'.$code->label().'")');
        }
        $sheet->setCellValue('E'.($start + 1), 'Boursier');
        $sheet->setCellValue('F'.($start + 1), '=COUNTIF('.$range('M').',"OUI")');
        $sheet->setCellValue('E'.($start + 2), 'Non boursier');
        $sheet->setCellValue('F'.($start + 2), '=COUNTIF('.$range('M').',"NON")');
        $sheet->getStyle("A{$start}:F".($start + 3))->getFont()->setBold(true);
    }
}
