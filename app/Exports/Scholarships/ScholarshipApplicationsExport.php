<?php

namespace App\Exports\Scholarships;

use App\Models\ScholarshipApplication;
use App\Support\ScholarshipApplicationFilters;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Paper scholarship files of the list, with its filters, as an Excel sheet. Every cell is written
 * as text (StringValueBinder): numbers keep their form and a value starting with "=" is never a formula.
 * No CIN, address, contact, reason or internal note.
 */
class ScholarshipApplicationsExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public const HEADINGS = ['N° DDB', 'Date de naissance', 'Nom', 'Prénoms', 'Établissement', 'Niveau', 'Parcours', 'Année universitaire', 'Statut', 'Date de réception', 'Date de décision'];

    /**
     * @param  array<string,mixed>  $filters
     * @param  list<string>  $relations
     */
    public function __construct(private array $filters, private array $relations) {}

    /** @return Builder<ScholarshipApplication> */
    public function query(): Builder
    {
        return ScholarshipApplicationFilters::apply(ScholarshipApplication::query()->with($this->relations), $this->filters);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return self::HEADINGS;
    }

    /**
     * @param  ScholarshipApplication  $application
     * @return list<string|null>
     */
    public function map($application): array
    {
        return [
            $application->reference(), $application->student->birth_date?->format('d/m/Y'),
            $application->student->last_name, $application->student->first_names, $application->studentRecord->establishment->name,
            $application->studentRecord->level->name, $application->studentRecord->program?->name, $application->academicYear->name,
            $application->displayStatus()['label'], $application->received_at->format('d/m/Y H:i'), $application->decided_at?->format('d/m/Y H:i'),
        ];
    }

    public function title(): string
    {
        return 'Dossiers de bourse';
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '15803D']]]];
    }

    /** Header row frozen and filterable, so a long list stays readable. */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$sheet->getHighestColumn().'1');
        }];
    }
}
